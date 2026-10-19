<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Service\SmsLoginService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Sms\Service\AbstractSmsSender;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Util\CountryDialCodeResolver;
use Shopwell\Core\System\Sms\Util\PhoneNumberMasker;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\System\Sms\Util\ShippedCountryDialCodeProvider;
use Shopwell\Core\Test\Assert\Serialization;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SmsLoginService::class)]
class SmsLoginServiceTest extends TestCase
{
    private const SECRET = 'unit-test-secret';

    private const E164 = '+4915112345678';

    private const NATIONAL = '15112345678';

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    private ArrayAdapter $cache;

    /**
     * The clock of the shop, so a test can spend the lifetime of a code without waiting for it.
     */
    private MockClock $clock;

    private AbstractSmsSender $smsSender;

    private LoggerInterface $logger;

    /**
     * The failure the sms component answers with, null when the message is recorded instead.
     */
    private ?SmsException $smsFailure = null;

    /**
     * @var list<array{templateRef: string, params: array<string, mixed>, phone: string, salesChannelId: string|null, countryIso: string|null}>
     */
    private array $sentMessages = [];

    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private array $logRecords = [];

    protected function setUp(): void
    {
        $this->customerRepository = new StaticEntityRepository([]);
        $this->cache = new ArrayAdapter();
        $this->clock = new MockClock('2026-01-01 12:00:00');
        $this->smsFailure = null;
        $this->sentMessages = [];
        $this->logRecords = [];

        $this->smsSender = static::createStub(AbstractSmsSender::class);

        // the message carries the only copy of the code, so the recording and the failure are one
        // behaviour of the stub and a test switches it by setting the failure
        $recordMessage = $this->recordMessage();
        $this->smsSender->method('sendTemplate')->willReturnCallback(
            function (
                string $templateRef,
                array $params,
                string $phone,
                ?Context $context = null,
                ?string $salesChannelId = null,
                ?string $countryIso = null,
            ) use ($recordMessage): void {
                if ($this->smsFailure !== null) {
                    throw $this->smsFailure;
                }

                $recordMessage($templateRef, $params, $phone, $context, $salesChannelId, $countryIso);
            }
        );

        $this->logger = static::createStub(LoggerInterface::class);
        $this->logger->method('info')->willReturnCallback($this->recordLog('info'));
        $this->logger->method('warning')->willReturnCallback($this->recordLog('warning'));
        $this->logger->method('error')->willReturnCallback($this->recordLog('error'));
    }

    public function testSendCodeSendsTheTemplateToTheCustomerNumber(): void
    {
        $this->customerRepository->searches = [new CustomerCollection([$this->createCustomer(self::E164)])];

        static::assertTrue($this->createService()->sendCode(self::E164, $this->createContext()));

        static::assertCount(1, $this->sentMessages);

        $message = $this->sentMessages[0];
        static::assertSame(SmsLoginService::TEMPLATE_REF, $message['templateRef']);
        static::assertSame(self::E164, $message['phone']);
        static::assertSame(TestDefaults::SALES_CHANNEL, $message['salesChannelId']);
        static::assertSame('DE', $message['countryIso']);
        static::assertSame('5', $message['params']['minutes']);
        static::assertMatchesRegularExpression('/^\d{6}$/', $message['params']['code']);
    }

    public function testSendCodeWritesOnlyTheHashOfTheCode(): void
    {
        $this->customerRepository->searches = [new CustomerCollection([$this->createCustomer(self::E164)])];

        $sentAt = $this->clock->now()->getTimestamp();
        $code = $this->sendCodeAndReadTheCode();

        $stored = $this->cachedCodeEntries();

        static::assertCount(1, $stored);
        static::assertSame(hash_hmac('sha256', $code, self::SECRET), $stored[0]['hash']);
        static::assertSame(0, $stored[0]['attempts']);
        static::assertStringNotContainsString($code, (string) json_encode($stored));

        // the moment the code dies is part of the entry, so nothing that is written later can move it
        static::assertIsInt($stored[0]['expiresAt']);
        static::assertGreaterThanOrEqual($sentAt + SmsLoginService::CODE_LIFETIME, $stored[0]['expiresAt']);
        static::assertLessThanOrEqual($sentAt + SmsLoginService::CODE_LIFETIME + 1, $stored[0]['expiresAt']);
    }

    public function testSendCodeDoesNothingForANumberWithoutCustomer(): void
    {
        $this->customerRepository->searches = [new CustomerCollection()];

        static::assertFalse($this->createService()->sendCode(self::E164, $this->createContext()));
        static::assertSame([], $this->sentMessages);
        static::assertSame([], $this->cachedCodeEntries());
    }

    public function testSendCodeRefusesANumberOfTwoCustomers(): void
    {
        $this->customerRepository->searches = [
            new CustomerCollection([$this->createCustomer(self::E164), $this->createCustomer(self::NATIONAL)]),
        ];

        static::assertFalse($this->createService()->sendCode(self::E164, $this->createContext()));
        static::assertSame([], $this->sentMessages);
        static::assertSame([], $this->cachedCodeEntries());
        static::assertSame('warning', $this->logRecords[0]['level']);
    }

    public function testSendCodeKeepsTheCodeWhenTheGatewayFails(): void
    {
        $this->customerRepository->searches = [new CustomerCollection([$this->createCustomer(self::E164)])];
        $this->smsFailure = SmsException::templateNotFound(SmsLoginService::TEMPLATE_REF);

        static::assertFalse($this->createService()->sendCode(self::E164, $this->createContext()));

        // the customer can retry within the lifetime of the code, the message is the only thing missing
        static::assertCount(1, $this->cachedCodeEntries());
    }

    public function testSendCodeNeverWritesTheCodeOrTheNumberToTheLog(): void
    {
        $this->customerRepository->searches = [new CustomerCollection([$this->createCustomer(self::E164)])];
        $this->smsFailure = SmsException::templateNotFound(SmsLoginService::TEMPLATE_REF);

        $this->createService()->sendCode(self::E164, $this->createContext());

        static::assertNotSame([], $this->logRecords);

        $logged = (string) json_encode(array_map(static fn (array $record): array => $record['context'], $this->logRecords));

        static::assertStringContainsString('+49151****5678', $logged);
        static::assertStringNotContainsString(self::E164, $logged);
        static::assertStringNotContainsString(self::NATIONAL, $logged);
        static::assertStringContainsString(SmsException::TEMPLATE_NOT_FOUND, $logged);
    }

    public function testVerifyCodeAcceptsTheCodeExactlyOnce(): void
    {
        $customer = $this->createCustomer(self::E164);
        $this->customerRepository->searches = [
            new CustomerCollection([$customer]),
            new CustomerCollection([$customer]),
            new CustomerCollection([$customer]),
        ];

        $service = $this->createService();
        $context = $this->createContext();
        $code = $this->sendCodeAndReadTheCode(self::E164, $context);

        $loggedIn = $service->verifyCode(self::E164, $code, $context);

        static::assertInstanceOf(CustomerEntity::class, $loggedIn);
        static::assertSame($customer->getId(), $loggedIn->getId());

        // the same code a second time is not good any more
        static::assertNull($service->verifyCode(self::E164, $code, $context));
    }

    public function testVerifyCodeRefusesAWrongCode(): void
    {
        $customer = $this->createCustomer(self::E164);
        $this->customerRepository->searches = [
            new CustomerCollection([$customer]),
            new CustomerCollection([$customer]),
        ];

        $service = $this->createService();
        $context = $this->createContext();
        $code = $this->sendCodeAndReadTheCode(self::E164, $context);

        static::assertNull($service->verifyCode(self::E164, $this->otherCodeOf($code), $context));

        $stored = $this->cachedCodeEntries();
        static::assertCount(1, $stored);
        static::assertSame(1, $stored[0]['attempts']);
    }

    public function testVerifyCodeDropsTheCodeAfterTheLastAttempt(): void
    {
        $customer = $this->createCustomer(self::E164);
        $this->customerRepository->searches = [
            new CustomerCollection([$customer]),
            new CustomerCollection([$customer]),
        ];

        $service = $this->createService();
        $context = $this->createContext();
        $code = $this->sendCodeAndReadTheCode(self::E164, $context);
        $wrongCode = $this->otherCodeOf($code);

        for ($attempt = 1; $attempt <= SmsLoginService::MAX_ATTEMPTS; ++$attempt) {
            static::assertNull($service->verifyCode(self::E164, $wrongCode, $context));
        }

        static::assertSame([], $this->cachedCodeEntries());

        // the right code cannot revive an entry that is gone
        static::assertNull($service->verifyCode(self::E164, $code, $context));
    }

    public function testWrongAttemptsDoNotExtendTheLifetimeOfTheCode(): void
    {
        $customer = $this->createCustomer(self::E164);
        $this->customerRepository->searches = [new CustomerCollection([$customer])];

        $service = $this->createService();
        $context = $this->createContext();
        $sentAt = $this->clock->now()->getTimestamp();
        $code = $this->sendCodeAndReadTheCode(self::E164, $context);
        $wrongCode = $this->otherCodeOf($code);

        // most of the lifetime of the message is over when the customer starts answering
        $this->clock->sleep(SmsLoginService::CODE_LIFETIME - 60);

        for ($attempt = 1; $attempt < SmsLoginService::MAX_ATTEMPTS; ++$attempt) {
            static::assertNull($service->verifyCode(self::E164, $wrongCode, $context));
        }

        $entries = $this->codeEntries();
        static::assertCount(1, $entries);
        $key = array_key_first($entries);
        static::assertIsString($key);

        // counting an attempt rewrites the entry, the deadline of the code is not part of that rewrite
        static::assertSame(SmsLoginService::MAX_ATTEMPTS - 1, $entries[$key]['attempts'] ?? null);
        static::assertSame($sentAt + SmsLoginService::CODE_LIFETIME, $entries[$key]['expiresAt'] ?? null);

        // one second past the lifetime of the message the code is worth nothing, however often it was
        // answered wrong - only a new code starts a new lifetime
        $this->clock->sleep(61);

        static::assertNull($service->verifyCode(self::E164, $code, $context));
        static::assertSame([], $this->codeEntries());
    }

    public function testACodeIsRefusedOnceItsLifetimeIsOverEvenWhenThePoolStillHasIt(): void
    {
        $customer = $this->createCustomer(self::E164);
        $this->customerRepository->searches = [new CustomerCollection([$customer])];

        $service = $this->createService();
        $context = $this->createContext();
        $code = $this->sendCodeAndReadTheCode(self::E164, $context);

        // the entry is still in the pool, only the deadline it carries is over: the deadline decides,
        // not the metadata of the pool
        $this->clock->sleep(SmsLoginService::CODE_LIFETIME);

        static::assertNull($service->verifyCode(self::E164, $code, $context));
        static::assertSame([], $this->codeEntries(), 'a code past its deadline is dropped, not read again');
    }

    public function testAThrottledVerificationIsLoggedWithTheMaskedNumber(): void
    {
        $this->createService()->logThrottledVerification(self::E164, $this->createContext());

        static::assertCount(1, $this->logRecords);
        static::assertSame('warning', $this->logRecords[0]['level']);

        $logged = (string) json_encode($this->logRecords[0]['context']);

        static::assertStringContainsString('+49151****5678', $logged);
        static::assertStringNotContainsString(self::E164, $logged);
        static::assertStringNotContainsString(self::NATIONAL, $logged);
    }

    public function testAThrottledVerificationOfANumberThatCannotBeReadWritesNoNumber(): void
    {
        $this->createService()->logThrottledVerification('not a number', $this->createContext());

        static::assertCount(1, $this->logRecords);
        static::assertSame('unreadable', $this->logRecords[0]['context']['phone'] ?? null);
    }

    public function testVerifyCodeRefusesANumberWithoutCode(): void
    {
        $this->customerRepository->searches = [new CustomerCollection([$this->createCustomer(self::E164)])];

        static::assertNull($this->createService()->verifyCode(self::E164, '123456', $this->createContext()));
    }

    public function testVerifyCodeMatchesACustomerThatStoredTheNationalNotation(): void
    {
        $customer = $this->createCustomer(self::NATIONAL);

        /** @var Criteria|null $criteria */
        $criteria = null;
        $this->customerRepository->searches = [
            static function (Criteria $searchCriteria, Context $context) use (&$criteria, $customer): CustomerCollection {
                $criteria = $searchCriteria;

                return new CustomerCollection([$customer]);
            },
            new CustomerCollection([$customer]),
        ];

        $service = $this->createService();
        $context = $this->createContext();
        $code = $this->sendCodeAndReadTheCode(self::E164, $context);

        $loggedIn = $service->verifyCode(self::E164, $code, $context);

        static::assertInstanceOf(CustomerEntity::class, $loggedIn);
        static::assertSame($customer->getId(), $loggedIn->getId());

        $filter = (string) json_encode($criteria?->getFilters());
        static::assertStringContainsString(self::E164, $filter);
        static::assertStringContainsString(self::NATIONAL, $filter);
    }

    public function testAcquireSendSlotRefusesASecondRequestWithinTheCooldown(): void
    {
        $service = $this->createService();
        $context = $this->createContext();

        static::assertTrue($service->acquireSendSlot(self::E164, $context));
        static::assertFalse($service->acquireSendSlot(self::E164, $context));
        // the slot belongs to the number, not to the notation it was written in
        static::assertFalse($service->acquireSendSlot('+49 151 12345678', $context));
    }

    public function testRateLimiterKeyReadsEveryNotationOfANumberTheSame(): void
    {
        $service = $this->createService();
        $context = $this->createContext();

        static::assertSame(
            $service->rateLimiterKey(self::E164, $context, '127.0.0.1'),
            $service->rateLimiterKey('+49 151 12345678', $context, '127.0.0.1')
        );

        static::assertNotSame(
            $service->rateLimiterKey(self::E164, $context, '127.0.0.1'),
            $service->rateLimiterKey(self::E164, $context, '127.0.0.2')
        );
    }

    public function testANumberWrittenWithoutItsCountryCodeIsReadAgainstTheShopCountry(): void
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setIso('CN');

        $customer = $this->createCustomer('+8613800000000');
        $this->customerRepository->searches = [
            new CustomerCollection([$customer]),
            new CustomerCollection([$customer]),
        ];

        $service = $this->createService();
        $context = Generator::generateSalesChannelContext(country: $country);

        $code = $this->sendCodeAndReadTheCode('13800000000', $context);

        static::assertSame('+8613800000000', $this->sentMessages[0]['phone']);
        static::assertNotNull($service->verifyCode('13800000000', $code, $context));
    }

    private function createService(): SmsLoginService
    {
        $normalizer = new PhoneNumberNormalizer(new CountryDialCodeResolver([new ShippedCountryDialCodeProvider()]));

        return new SmsLoginService(
            $this->customerRepository,
            $this->smsSender,
            $this->cache,
            $normalizer,
            new PhoneNumberMasker(),
            $this->logger,
            $this->clock,
            self::SECRET,
        );
    }

    private function createContext(): SalesChannelContext
    {
        return Generator::generateSalesChannelContext();
    }

    /**
     * Sends a code and answers the code the service generated, which only the message carries.
     */
    private function sendCodeAndReadTheCode(string $phone = self::E164, ?SalesChannelContext $context = null): string
    {
        $sentBefore = \count($this->sentMessages);

        static::assertTrue($this->createService()->sendCode($phone, $context ?? $this->createContext()));
        static::assertCount($sentBefore + 1, $this->sentMessages);

        $code = $this->sentMessages[$sentBefore]['params']['code'];
        static::assertIsString($code);

        return $code;
    }

    /**
     * The entries the service wrote, read over the pool so the test does not repeat the key format.
     *
     * @return list<array<string, mixed>>
     */
    private function cachedCodeEntries(): array
    {
        return array_values($this->codeEntries());
    }

    /**
     * The entries the service wrote, keyed by the key the pool stored them under.
     *
     * @return array<string, array<string, mixed>>
     */
    private function codeEntries(): array
    {
        $entries = [];

        foreach ($this->cache->getValues() as $key => $value) {
            if (!str_contains((string) $key, 'code.')) {
                continue;
            }

            // the pool stores the payload serialized, exactly like the pool of the shop does
            if (\is_string($value)) {
                $entries[(string) $key] = Serialization::assertUnserializedIsArray($value);

                continue;
            }

            static::assertIsArray($value);
            $entries[(string) $key] = $value;
        }

        return $entries;
    }

    /**
     * The moment the cache drops the entry, taken from the metadata of the pool itself.
     */
    private function createCustomer(string $phoneNumber): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setEmail('customer-' . Uuid::randomHex() . '@example.com');
        $customer->setPhoneNumber($phoneNumber);
        $customer->setActive(true);
        $customer->setGuest(false);

        return $customer;
    }

    /**
     * A code of the same shape that differs from the sent one.
     */
    private function otherCodeOf(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    /**
     * @return callable(string, array<string, mixed>, string, Context|null, string|null, string|null):void
     */
    private function recordMessage(): callable
    {
        return function (
            string $templateRef,
            array $params,
            string $phone,
            ?Context $context = null,
            ?string $salesChannelId = null,
            ?string $countryIso = null,
        ): void {
            $this->sentMessages[] = [
                'templateRef' => $templateRef,
                'params' => $params,
                'phone' => $phone,
                'salesChannelId' => $salesChannelId,
                'countryIso' => $countryIso,
            ];
        };
    }

    /**
     * @return callable(string, array<string, mixed>):void
     */
    private function recordLog(string $level): callable
    {
        return function (string $message, array $context = []) use ($level): void {
            $this->logRecords[] = ['level' => $level, 'message' => $message, 'context' => $context];
        };
    }
}
