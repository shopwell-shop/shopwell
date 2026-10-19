<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Service;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Sms\Service\AbstractSmsSender;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Util\PhoneNumber;
use Shopwell\Core\System\Sms\Util\PhoneNumberMaskerInterface;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The verification code of the login by sms: the code that is sent, the code that is checked and the
 * customer a number belongs to.
 *
 * The state of a code lives in the cache and nowhere else: a shop must be able to offer the login
 * without a migration and without a table that has to be pruned, and a code that was never confirmed
 * is worth nothing after its lifetime. Only a keyed hash of the code is written, never the code
 * itself, so a copy of the cache is not a copy of the credentials of the customers.
 *
 * Two rules run through the whole class:
 *
 * - Nothing the caller does may reveal whether a number belongs to a customer. The lookup, the
 *   sending and the throttling answer the same way for a number of a customer and for a number
 *   nobody registered, and the only place that knows the difference is the log.
 * - A code is good exactly once. The entry is dropped the moment it matched, a mismatch counts
 *   against a small budget and the entry is dropped as well when the budget is used up, so a code
 *   cannot be brute forced with an unlimited number of attempts.
 * - A code has one deadline. The moment it dies is written into the entry, so counting an attempt
 *   or reading the code never pushes it back - the lifetime the message announces is the lifetime
 *   the code has.
 *
 * @internal
 */
#[Package('checkout')]
class SmsLoginService
{
    /**
     * The logical sms template of the code, seeded by the migration of the sms component.
     */
    public const TEMPLATE_REF = 'customer.otp.login';

    /**
     * How long a code is valid, in seconds.
     */
    public const CODE_LIFETIME = 300;

    /**
     * How long a number has to wait before another code is sent for it, in seconds.
     */
    public const RESEND_COOLDOWN = 60;

    /**
     * How often a code may be answered wrong before it is dropped.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * The minutes the template of the message announces, see CODE_LIFETIME.
     */
    private const TEMPLATE_MINUTES = '5';

    private const CODE_MIN = 100000;

    private const CODE_MAX = 999999;

    private const CACHE_PREFIX = 'shopwell.sms_login.';

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param string $kernelSecret the secret the code is hashed with before it is written to the cache
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly AbstractSmsSender $smsSender,
        private readonly CacheInterface $cache,
        private readonly PhoneNumberNormalizerInterface $normalizer,
        private readonly PhoneNumberMaskerInterface $masker,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly string $kernelSecret,
    ) {
    }

    /**
     * The key of the rate limiter of the code request: the canonical form of the number and the
     * client that asked for it.
     *
     * The number is read the same way as everywhere else, so a limit is spent on the number and not
     * on the notation it was written in - a caller cannot multiply its requests by spacing them
     * differently.
     */
    public function rateLimiterKey(string $phone, SalesChannelContext $context, string $clientIp): string
    {
        return $this->identifier($phone, $context) . '|' . $clientIp;
    }

    /**
     * Reserves the right to send one code for a number.
     *
     * It is a separate step so the storefront can tell the customer that the last code was sent a
     * moment ago - the slot is taken for every request, whether a code ends up being sent or not,
     * because a slot that was only taken for a known number would answer differently for the two
     * cases and would hand out the very information the code request must not reveal.
     */
    public function acquireSendSlot(string $phone, SalesChannelContext $context): bool
    {
        $key = $this->cooldownKey($phone, $context);
        $acquired = false;

        // a hit means the slot was taken less than RESEND_COOLDOWN seconds ago and the callback,
        // which is the only place that takes it, is not called
        $this->cache->get($key, static function (ItemInterface $item) use (&$acquired): bool {
            $item->expiresAfter(self::RESEND_COOLDOWN);
            $acquired = true;

            return true;
        });

        return $acquired;
    }

    /**
     * Sends a code to the number of the single customer it belongs to.
     *
     * The answer is only for the caller that wants to write a log line: the storefront shows the
     * same message for a sent code, for a number nobody registered and for a number that cannot be
     * read, otherwise the form would be a query for the numbers of the customers of the shop.
     *
     * @return bool true when a code was created and handed to the sms component
     */
    public function sendCode(string $phone, SalesChannelContext $context): bool
    {
        $number = $this->normalize($phone, $context);

        if ($number === null) {
            $this->logger->info('A login by sms was requested with a number that cannot be read.', [
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            return false;
        }

        if (!$this->findCustomer($number, $phone, $context) instanceof CustomerEntity) {
            $this->logger->info('A login by sms was requested for a number without exactly one active customer.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            return false;
        }

        $code = (string) random_int(self::CODE_MIN, self::CODE_MAX);
        $this->storeCode($number, $code, $context->getSalesChannelId());

        try {
            $this->smsSender->sendTemplate(
                self::TEMPLATE_REF,
                ['code' => $code, 'minutes' => self::TEMPLATE_MINUTES],
                $number->e164(),
                $context->getContext(),
                $context->getSalesChannelId(),
                $number->countryIso(),
            );
        } catch (SmsException $exception) {
            // the exception is not written to the log: the message of a gateway failure repeats the
            // parameters of the message, and one of them is the code. The stable error code names the
            // cause without carrying the payload, the code itself stays valid for its lifetime so a
            // customer can retry the request.
            $this->logger->error('The sms of a login code could not be sent.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $context->getSalesChannelId(),
                'errorCode' => $exception->getErrorCode(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Checks a code and answers the customer it was sent to.
     *
     * @return CustomerEntity|null the customer to log in, null for every reason a code does not match
     */
    public function verifyCode(string $phone, string $code, SalesChannelContext $context): ?CustomerEntity
    {
        $number = $this->normalize($phone, $context);

        if ($number === null) {
            $this->logger->info('A login by sms was confirmed with a number that cannot be read.', [
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            return null;
        }

        $entry = $this->readCode($number, $context->getSalesChannelId());

        if ($entry === null) {
            // no code, an expired code and a code of another number are one case for the caller
            $this->logger->info('A login by sms was confirmed without a code that is still valid.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            return null;
        }

        if (!hash_equals($entry['hash'], $this->hashCode($code))) {
            $this->countFailedAttempt($number, $context->getSalesChannelId(), $entry);

            return null;
        }

        // a code is good exactly once, also when the customer it belongs to cannot be logged in
        $this->deleteCode($number, $context->getSalesChannelId());

        $customer = $this->findCustomer($number, $phone, $context);

        if (!$customer instanceof CustomerEntity) {
            $this->logger->warning('A login by sms matched a code but not exactly one active customer.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            return null;
        }

        return $customer;
    }

    /**
     * Writes the entry of a code.
     *
     * It is the counterpart of sendCode() for the callers that do not send a message themselves - a
     * test, or an extension that delivers the code over a channel of its own - so the whole flow can
     * be driven without a gateway.
     *
     * @internal
     */
    public function storeCode(PhoneNumber $number, string $code, string $salesChannelId): void
    {
        $this->writeCode($number, $salesChannelId, $this->hashCode($code), 0, $this->now() + self::CODE_LIFETIME);
    }

    /**
     * Writes the line of a caller that was throttled while answering a code.
     *
     * The storefront answers such a caller exactly like one that wrote a wrong code, so this line is
     * the only place that tells an operator that a number is being hammered - and the number is
     * masked here like everywhere else.
     */
    public function logThrottledVerification(string $phone, SalesChannelContext $context): void
    {
        $number = $this->normalize($phone, $context);

        $this->logger->warning('A login by sms was throttled while answering a code.', [
            // a number that cannot be read is not written at all
            'phone' => $number === null ? 'unreadable' : $this->masker->mask($number),
            'salesChannelId' => $context->getSalesChannelId(),
        ]);
    }

    /**
     * @return array{hash: string, attempts: int, expiresAt: int}|null
     */
    private function readCode(PhoneNumber $number, string $salesChannelId): ?array
    {
        $entry = $this->cache->get(
            $this->codeKey($number, $salesChannelId),
            static function (ItemInterface $item, bool &$save): ?array {
                // a read must not write: the caller only wants to know whether an entry exists
                $save = false;

                return null;
            }
        );

        if (!\is_array($entry)) {
            return null;
        }

        // the entry is written by writeCode() and by nobody else, so the three keys are always there
        $hash = $entry['hash'] ?? '';
        $attempts = $entry['attempts'] ?? 0;
        $expiresAt = $entry['expiresAt'] ?? 0;

        if (!\is_string($hash) || !\is_int($attempts) || !\is_int($expiresAt)) {
            return null;
        }

        // the lifetime is the one the storefront announced in the message and not the one of the
        // cache entry: a pool that kept an entry longer than that must not make an old code valid
        if ($expiresAt <= $this->now()) {
            $this->deleteCode($number, $salesChannelId);
            $this->logger->info('A login by sms found a code that was past its lifetime.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $salesChannelId,
            ]);

            return null;
        }

        return ['hash' => $hash, 'attempts' => $attempts, 'expiresAt' => $expiresAt];
    }

    /**
     * Writes an entry that expires at the given moment.
     *
     * The deadline belongs to the code, not to the write: every rewrite - a counted attempt included -
     * keeps it, so a caller cannot buy more time by answering the code wrong, and the cache entry is
     * dropped no later than the code itself.
     */
    private function writeCode(PhoneNumber $number, string $salesChannelId, string $hash, int $attempts, int $expiresAt): void
    {
        $key = $this->codeKey($number, $salesChannelId);

        // a code replaces the one that is still valid, and the callback below is the only writer
        $this->cache->delete($key);

        $lifetime = max(1, $expiresAt - $this->now());
        $entry = ['hash' => $hash, 'attempts' => $attempts, 'expiresAt' => $expiresAt];

        $this->cache->get($key, static function (ItemInterface $item) use ($entry, $lifetime): array {
            $item->expiresAfter($lifetime);

            return $entry;
        });
    }

    private function deleteCode(PhoneNumber $number, string $salesChannelId): void
    {
        $this->cache->delete($this->codeKey($number, $salesChannelId));
    }

    /**
     * @param array{hash: string, attempts: int, expiresAt: int} $entry
     */
    private function countFailedAttempt(PhoneNumber $number, string $salesChannelId, array $entry): void
    {
        $attempts = $entry['attempts'] + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            // the budget of a code is spent, the number needs a new one
            $this->deleteCode($number, $salesChannelId);
            $this->logger->warning('A login by sms used up the attempts of its code.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $salesChannelId,
            ]);

            return;
        }

        if ($entry['expiresAt'] <= $this->now()) {
            // the wrong answer came too late for the code to be counted, and there is nothing left
            // to write: the entry would only hold the answer to a code that is not valid any more
            $this->deleteCode($number, $salesChannelId);

            return;
        }

        $this->writeCode($number, $salesChannelId, $entry['hash'], $attempts, $entry['expiresAt']);
    }

    /**
     * The single active customer of a number, or null when the number matches nobody or more than
     * one account - both cases are a credentials failure for the caller, a number that two customers
     * share must not log either of them in.
     */
    private function findCustomer(PhoneNumber $number, string $writtenPhone, SalesChannelContext $context): ?CustomerEntity
    {
        $candidates = array_values(array_unique(array_filter([
            $number->e164(),
            $number->nationalNumber(),
            $number->dialCode() . $number->nationalNumber(),
            // a shop that never canonicalised the number of a customer stored what the customer typed
            mb_trim($writtenPhone),
        ], static fn (string $candidate): bool => $candidate !== '')));

        if ($candidates === []) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, array_map(
                    static fn (string $candidate): EqualsFilter => new EqualsFilter('phoneNumber', $candidate),
                    $candidates
                )),
                new EqualsFilter('active', true),
                new EqualsFilter('guest', false),
            ])
        );
        $criteria->setLimit(2);

        $customers = $this->customerRepository->search($criteria, $context->getContext())->getEntities();

        if ($customers->count() > 1) {
            $this->logger->warning('A number is registered for more than one customer, the login by sms refuses it.', [
                'phone' => $this->masker->mask($number),
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            return null;
        }

        $customer = $customers->first();

        return $customer instanceof CustomerEntity ? $customer : null;
    }

    /**
     * Reads the written number, or null when it cannot be read.
     *
     * The storefront form does not ask for the country of the number, so a number written without
     * its country code is read against the country of the sales channel - the country the shop
     * sells to - and never against a country the shop would have to guess.
     */
    private function normalize(string $phone, SalesChannelContext $context): ?PhoneNumber
    {
        try {
            return $this->normalizer->normalize($phone);
        } catch (SmsException) {
            // a number of the home country of the shop is normally written without its dial code
        }

        try {
            return $this->normalizer->normalize($phone, $context->getShippingLocation()->getCountry()->getIso());
        } catch (SmsException) {
            return null;
        }
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->kernelSecret);
    }

    /**
     * The current second of the clock of the shop, so a test can decide what "now" is.
     */
    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    private function codeKey(PhoneNumber $number, string $salesChannelId): string
    {
        // a key is a name for a place in the cache, not a credential: it names the number so the
        // entry of a number cannot be read under the key of another one, and it is hashed so the
        // number itself does not have to travel through the cache backend
        return self::CACHE_PREFIX . 'code.' . Hasher::hash($salesChannelId . '|' . $number->e164());
    }

    private function cooldownKey(string $phone, SalesChannelContext $context): string
    {
        return self::CACHE_PREFIX . 'cooldown.' . Hasher::hash($context->getSalesChannelId() . '|' . $this->identifier($phone, $context));
    }

    /**
     * The number as far as the cache and the rate limiter are concerned: the canonical form when the
     * number can be read, the written form when it cannot.
     */
    private function identifier(string $phone, SalesChannelContext $context): string
    {
        return $this->normalize($phone, $context)?->e164() ?? mb_strtolower(mb_trim($phone));
    }
}
