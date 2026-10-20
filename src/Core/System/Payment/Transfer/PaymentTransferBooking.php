<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferRequest;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\Api\Struct\PaymentTransferDraft;

/**
 * Books the intent to pay: the part of a payout that happens before any channel is involved.
 *
 * It exists because the controller would otherwise carry sixty lines of glue - find the app, check the
 * interface and the channel, create or reuse the recipient, build the request - and that glue is the last
 * gate before money leaves the platform. A class can be asserted directly; a controller action cannot.
 *
 * Two invariants are visible here:
 *
 *  1. **Nothing in this class talks to a channel.** No gateway is built, no credential is decrypted for a
 *     payout, no request leaves the platform. Booking a payout moves nothing; only
 *     {@see PaymentTransferSubmitter} does.
 *  2. **The app is the tenant of everything.** The recipient, the interface and the channel are all
 *     validated against the app the operator picked, because the credentials of the payout come from that
 *     app - a recipient of another app does not exist for this one, which is what
 *     {@see PaymentTransferService::create()} enforces by its app scoped lookup.
 *
 * The identity matrix (which identity type an interface accepts, which fields it demands, the 149 byte
 * limit of the encrypted column) is deliberately **not** re-implemented here: it belongs to
 * {@see PaymentTransferRecipientService}, which is the only place that may know it. This class only makes
 * sure that a new recipient brings the fields that service needs to be called at all.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferBooking
{
    /**
     * The prefix of a generated out number - `TF` for transfer, followed by the moment and eight random
     * characters, at most 22 of the 30 characters the interfaces allow.
     */
    private const OUT_NO_PREFIX = 'TF';

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentTransferRecipientService $recipientService,
        private readonly PaymentTransferService $transferService,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Books the payout the draft describes and returns the row in `created`.
     *
     * A replayed out number returns the payout that is already booked - the idempotency of the business
     * case is owned by {@see PaymentTransferService::create()}. What is checked here is the other half of
     * a replay: the same number with a **different** recipient, amount or currency is not a replay but two
     * business cases fighting over one number, and the caller has to learn that instead of silently
     * getting somebody else's payout back.
     *
     * @throws PaymentException
     */
    public function book(PaymentTransferDraft $draft): PaymentTransferEntity
    {
        $app = $this->resolveApp($draft->paymentAppId);

        // a draft that reaches this method through the controller is already validated, but the booking
        // is the domain boundary and re-checks what the money depends on
        $amount = $draft->amount;

        if ($amount === null || $amount <= 0 || $amount > PaymentTransferDraft::MAX_AMOUNT) {
            throw PaymentException::transferAmountInvalid();
        }

        $providerName = (string) $draft->provider;
        $channelName = (string) $draft->channel;
        $currency = strtoupper((string) $draft->currency);

        $provider = $this->assertProviderAuthorized($app, $providerName);
        $this->assertChannelEnabled($app, $provider, $channelName);
        $this->assertCurrency($provider, $channelName, $currency);
        $this->assertIdentity($draft);

        $recipientId = $this->resolveRecipientId($app, $draft, $providerName, $amount);

        $outNo = $draft->outNo ?? $this->generateOutNo();
        $this->assertOutNoIsFree($app, $outNo, $recipientId, $amount, $currency);

        return $this->transferService->create(
            $app,
            new PaymentTransferRequest(
                outNo: $outNo,
                recipientId: $recipientId,
                provider: $providerName,
                channel: $channelName,
                amount: $amount,
                currency: $currency,
                metadata: $draft->note === null ? null : ['note' => $draft->note],
            )
        );
    }

    /**
     * The app a payout is booked for, loaded with the authorizations and channel switches the modal and
     * the booking both need.
     *
     * The submit path of the controller uses this as well: there the id comes from the transfer itself and
     * from nowhere else, which is what makes "pay out of the wrong merchant account" impossible instead of
     * merely unlikely.
     *
     * @throws PaymentException
     */
    public function resolveApp(?string $paymentAppId): PaymentAppEntity
    {
        if ($paymentAppId === null || trim($paymentAppId) === '') {
            throw PaymentException::missingParameter('paymentAppId');
        }

        $criteria = new Criteria([$paymentAppId]);
        $criteria->addAssociation('providers');
        $criteria->addAssociation('channels');

        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        // "does not exist" and "is switched off" are one answer for the caller: neither app can pay out,
        // and saying which of the two it is would describe rows of another tenant
        if (!$app instanceof PaymentAppEntity || !$app->active) {
            throw PaymentException::transferAppNotAvailable($paymentAppId);
        }

        return $app;
    }

    /**
     * The interface has to be authorized for the app and has to be able to pay out at all.
     *
     * @throws PaymentException
     */
    private function assertProviderAuthorized(PaymentAppEntity $app, string $providerName): PaymentProvider
    {
        foreach ($app->providers ?? [] as $authorization) {
            if (!$authorization instanceof PaymentAppProviderEntity) {
                continue;
            }

            if ($authorization->providerTechnicalName !== $providerName || !$authorization->enabled) {
                continue;
            }

            if (!$this->providerRegistry->has($providerName)) {
                break;
            }

            $provider = $this->providerRegistry->get($providerName);

            // announcing a payout and not executing it is the one mismatch the caller can not see: the
            // guard belongs here, before the intent is booked
            if (!$provider->supports(PaymentCapability::TRANSFER)) {
                throw PaymentException::providerLacksCapability($providerName, PaymentCapability::TRANSFER->value);
            }

            return $provider;
        }

        throw PaymentException::providerNotEnabled($providerName, $app->id);
    }

    /**
     * An interface authorization does not mean every product of that interface is switched on for the app.
     *
     * @throws PaymentException
     */
    private function assertChannelEnabled(PaymentAppEntity $app, PaymentProvider $provider, string $channelName): void
    {
        foreach ($app->channels ?? [] as $row) {
            if (!$row instanceof PaymentAppChannelEntity) {
                continue;
            }

            if ($row->channelTechnicalName !== $channelName || !$row->enabled) {
                continue;
            }

            if ($provider->hasChannel($channelName)) {
                return;
            }
        }

        throw PaymentException::channelNotEnabled($channelName, $app->id);
    }

    /**
     * The currency has to be a code and one the channel can settle in.
     *
     * The "alipay and wechat pay out in yuan only" rule is not repeated here: it is enforced by
     * {@see PaymentTransferService::create()}, which is the only writer and therefore the only place that
     * has to know it.
     *
     * @throws PaymentException
     */
    private function assertCurrency(PaymentProvider $provider, string $channelName, string $currency): void
    {
        if (\preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw PaymentException::currencyNotSupported($channelName, $currency);
        }

        $channel = $provider->channels[$channelName] ?? null;

        if ($channel instanceof PaymentChannel && !$channel->supportsCurrency($currency)) {
            throw PaymentException::currencyNotSupported($channelName, $currency);
        }
    }

    /**
     * A new recipient has to bring the two fields the recipient service works with. Which identity types
     * the interface accepts, which additional fields it demands and how long an identity may be is
     * validated there - one truth for the matrix.
     *
     * @throws PaymentException
     */
    private function assertIdentity(PaymentTransferDraft $draft): void
    {
        if ($draft->usesExistingRecipient()) {
            return;
        }

        if ($draft->identityType === null) {
            throw PaymentException::transferRecipientIncomplete('identityType');
        }

        if ($draft->identityValue === null) {
            throw PaymentException::transferRecipientIncomplete('identityValue');
        }
    }

    /**
     * The id of the recipient the payout goes to - created on first use, or the one the operator picked.
     *
     * An existing recipient is handed on by id and **not** loaded here: the app scoped lookup of
     * {@see PaymentTransferService::create()} is what makes a recipient of another app "not exist" for
     * this one, and a second lookup with a weaker scope would be a second, weaker truth.
     *
     * @throws PaymentException
     */
    private function resolveRecipientId(
        PaymentAppEntity $app,
        PaymentTransferDraft $draft,
        string $providerName,
        int $amount,
    ): string {
        if ($draft->recipientId !== null) {
            return $draft->recipientId;
        }

        $recipient = $this->recipientService->createOrGet(
            $app,
            $providerName,
            (string) $draft->identityType,
            (string) $draft->identityValue,
            $draft->holderName,
            $draft->bankInfo,
        );

        // the amount decides whether wechat demands the name of the holder - the recipient service owns
        // that rule, so it is asked instead of being mirrored here
        $this->recipientService->assertPayoutReady($recipient, $amount);

        return $recipient->id;
    }

    /**
     * A hand filled out number that already names a **different** payout is refused: the platform would
     * otherwise answer "already booked" with a payout the operator never meant, and the money of the
     * intended one would never move.
     *
     * @throws PaymentException
     */
    private function assertOutNoIsFree(
        PaymentAppEntity $app,
        string $outNo,
        string $recipientId,
        int $amount,
        string $currency,
    ): void {
        $existing = $this->transferService->getByOutNo($app, $outNo);

        if ($existing === null) {
            return;
        }

        $samePayout = $existing->recipientId === $recipientId
            && $existing->amount === $amount
            && $existing->currency === $currency;

        if (!$samePayout) {
            throw PaymentException::transferOutNoTaken($outNo);
        }
    }

    /**
     * `TF` + `ymdHis` + 8 random characters. The moment makes a payout findable by eye, the random part
     * makes a collision between two operators booking in the same second unlikely - and the out number
     * stays far below the 30 characters paypal derives its batch ids from.
     */
    private function generateOutNo(): string
    {
        return self::OUT_NO_PREFIX
            . $this->clock->now()->format('ymdHis')
            . strtoupper(bin2hex(random_bytes(4)));
    }
}
