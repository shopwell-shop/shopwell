<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferRequest;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;

/**
 * Books the life of a payout: creates the transfer, records what the caller learned from the channel
 * and keeps the timestamps of the row in step with the state machine.
 *
 * The service never talks to a channel. Whoever submits the payout or receives the notification hands
 * the outcome in; this class only decides which state that outcome means and which bookkeeping belongs
 * to it.
 *
 * Idempotency is guaranteed by the unique key over (app, out number): asking for the same payout twice
 * returns the very same transfer instead of paying twice. The out number is reused by every retry -
 * a second number would be a second payout, never a retry.
 *
 * Amounts are integers in the smallest unit of the currency and are never converted here: alipay
 * wants a decimal string of yuan, paypal a decimal string, wechat and stripe the smallest unit, and
 * each adapter converts at its own boundary.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferService
{
    private const PROVIDER_ALIPAY = 'alipay';
    private const PROVIDER_WECHAT = 'wechat';

    /**
     * The interfaces whose payouts are settled in yuan only. Stripe and paypal are deliberately not
     * on the list: what they can pay out is a property of the merchant account (stripe) or of the
     * receiving account (paypal) and is validated by the channel adapter, not by the platform.
     */
    private const CNY_ONLY_PROVIDERS = [self::PROVIDER_ALIPAY, self::PROVIDER_WECHAT];

    /**
     * Wechat caps `out_bill_no` at 32 characters of `[0-9a-zA-Z]`, alipay caps `out_biz_no` at 64,
     * paypal caps the batch and item ids it derives from ours at 30. The strictest limit wins - a
     * payout that cannot be sent under its number must not be booked.
     *
     * Paypal is the reason the number is 30 and not 32: paypal does not take the out number itself,
     * it takes a `sender_batch_id` / `sender_item_id` derived from it, and a number longer than the
     * ceiling can only be shortened lossily. A lossy id is not a cosmetic problem - two out numbers
     * that differ only in their last characters would derive the same batch, paypal would answer
     * "duplicate batch", and the platform would read that as "the retry went through" while the money
     * of one payout went out under the number of the other. Fitting the number to the tightest
     * interface keeps the derivation an identity, which is the only form that survives a notification
     * that carries the derived id and not the out number.
     */
    private const OUT_NO_PATTERN = '/^[0-9a-zA-Z]{1,30}\z/';

    /**
     * The width of `payment_transfer.failure_reason`. Channel messages are not bounded by us, so every
     * writer is cut here instead of trusting that its caller measured right.
     */
    private const FAILURE_REASON_LIMIT = 255;

    /**
     * Wechat closes a transfer the shopper never confirmed after 24 hours and refunds it. The window
     * may be exceeded slightly, so the moment is a hint for the query task, not a guarantee.
     */
    private const WECHAT_CONFIRM_WINDOW = '+24 hours';

    /**
     * @param EntityRepository<PaymentTransferCollection> $transferRepository
     * @param EntityRepository<PaymentTransferRecipientCollection> $recipientRepository
     */
    public function __construct(
        private readonly EntityRepository $transferRepository,
        private readonly EntityRepository $recipientRepository,
        private readonly PaymentTransferStateHandler $stateHandler,
        private readonly PaymentTransferRecipientService $recipientService,
        private readonly PaymentCredentialCipher $cipher,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Books a payout in `created` - the money has not moved yet, the row only records the intent.
     *
     * @throws PaymentException
     */
    public function create(PaymentAppEntity $app, PaymentTransferRequest $request): PaymentTransferEntity
    {
        if ($request->amount <= 0) {
            throw PaymentException::transferAmountInvalid();
        }

        if (\preg_match(self::OUT_NO_PATTERN, $request->outNo) !== 1) {
            throw PaymentException::transferOutNoInvalid($request->outNo);
        }

        // the idempotent replay answers before anything else is validated: the payout of this out
        // number is already booked, and re-validating a recipient that may have changed since would
        // turn a harmless replay into an error
        $existing = $this->getByOutNo($app, $request->outNo);
        if ($existing !== null) {
            return $existing;
        }

        $recipient = $this->getRecipient($app, $request->recipientId);

        if ($recipient->provider !== $request->provider) {
            // the recipient was created for another interface: its identity means nothing to this one
            throw PaymentException::transferIdentityNotSupported($request->provider, $recipient->identityType);
        }

        $this->assertCurrencySupported($request->provider, $request->currency);
        $this->recipientService->assertPayoutReady($recipient, $request->amount);

        // the identity of the recipient is a credential and the payout is built from its plain text.
        // A row that can not be decrypted (a rotated application secret, a row written before the
        // encryption existed) is refused here, not at the interface after the intent to pay exists.
        $this->cipher->decryptValue($recipient->identityValue);

        $now = $this->clock->now();
        $id = Uuid::randomHex();

        $row = [
            'id' => $id,
            'paymentAppId' => $app->id,
            'transferNo' => $this->generateTransferNo($now),
            'outNo' => $request->outNo,
            'recipientId' => $recipient->id,
            'provider' => $request->provider,
            'channel' => $request->channel,
            // already in the smallest unit of the currency - converting is the channel's job
            'amount' => $request->amount,
            'currency' => $request->currency,
            // the parameters a payout is built from have to survive the call: the submission happens
            // later, may be repeated days afterwards and is then built in a task that has no caller to
            // ask - the adapter refuses a payout whose scene is gone
            'channelParams' => $request->channelParams === [] ? null : $request->channelParams,
            'stateId' => $this->initialStateIdLoader->get(PaymentTransferStates::STATE_MACHINE),
            // the dal never writes the property initial value - it only consults
            // EntityDefinition::getDefaults(), and the column is not nullable without a ddl default,
            // so an insert that leaves the field out fails. Same story as paidAmount in
            // PaymentOrderService::create() and attemptCount in PaymentCycleService::scheduleCycle().
            'submitAttempts' => 0,
            'expireAt' => $request->provider === self::PROVIDER_WECHAT
                ? $now->modify(self::WECHAT_CONFIRM_WINDOW)
                : null,
            'metadata' => $request->metadata,
        ];

        try {
            $this->transferRepository->create([$row], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            // the unique key over (app, out number) was hit: the same payout was booked between our
            // lookup and our insert, and that row is the answer - not a second payout
            $race = $this->getByOutNo($app, $request->outNo);

            if ($race === null) {
                throw PaymentException::transferConflict($request->outNo);
            }

            return $race;
        }

        return $this->getById($id);
    }

    /**
     * Moves the transfer to `submitted` right before the request leaves the platform and remembers
     * what the interface handed out.
     *
     * `$confirmPayload` is wechat's one time `package_info`: the shopper accepts the transfer with it
     * in their app, and it can not be recovered - losing it means canceling the transfer and sending
     * it again under the same out number.
     *
     * The transfer is only submittable while it is `created`. Submitting it again is not a retry but a
     * second payout; a retry always queries the interface first.
     *
     * @throws PaymentException
     */
    public function markSubmitted(
        PaymentTransferEntity $transfer,
        string $providerRef,
        ?string $providerStageRef = null,
        ?string $confirmPayload = null,
    ): void {
        $state = $transfer->statusMachineState?->getTechnicalName();

        if ($state !== null && $state !== PaymentTransferStates::STATE_CREATED) {
            throw PaymentException::transferAlreadyFinalized($transfer->outNo, $state);
        }

        $this->stateHandler->submit($transfer->id);

        $this->transferRepository->update([[
            'id' => $transfer->id,
            'providerRef' => $providerRef,
            'providerStageRef' => $providerStageRef,
            'confirmPayload' => $this->confirmPayloadRow($confirmPayload),
            'submittedAt' => $this->clock->now(),
            'submitAttempts' => $transfer->submitAttempts + 1,
        ]], Context::createDefaultContext());
    }

    /**
     * Wechat only: the transfer waits for the shopper to accept it in their app.
     *
     * @throws PaymentException
     */
    public function markAwaitingConfirm(PaymentTransferEntity $transfer, ?string $confirmPayload = null): void
    {
        $this->stateHandler->awaitConfirm($transfer->id);

        if ($confirmPayload === null) {
            return;
        }

        $this->transferRepository->update([[
            'id' => $transfer->id,
            'confirmPayload' => $this->confirmPayloadRow($confirmPayload),
        ]], Context::createDefaultContext());
    }

    /**
     * The interface took the payout and moves the money asynchronously - from `submitted` (the answer
     * came instead of a confirmation) or from `awaiting_user_confirm` (the shopper accepted).
     *
     * @throws PaymentException
     */
    public function markProcessing(PaymentTransferEntity $transfer): void
    {
        $this->stateHandler->process($transfer->id);
    }

    /**
     * The money reached the recipient.
     *
     * `null` means the interface did not report when it settled - the platform then books the moment it
     * learned about it, which is the same fact one delivery later.
     *
     * @throws PaymentException
     */
    public function markSucceeded(PaymentTransferEntity $transfer, ?\DateTimeInterface $settledAt = null): void
    {
        $this->stateHandler->succeed($transfer->id);

        $this->transferRepository->update([[
            'id' => $transfer->id,
            'settledAt' => $settledAt ?? $this->clock->now(),
            // a settled transfer has no failure left: keeping one would make a later query believe
            // there is still something to resolve
            'failureCode' => null,
            'failureReason' => null,
        ]], Context::createDefaultContext());
    }

    /**
     * Books a failure - one method for every origin state on purpose.
     *
     * `succeeded -> failed` is not a different action, only a different origin: stripe reports a
     * payout as `paid` and changes it to `failed` within five business days, and the caller can not
     * tell the two apart before it has read the current state. The settled timestamp is cleared when
     * the money is taken back, so the row does not claim a settlement that no longer exists.
     *
     * The reason is cut here and not by the caller: it is the raw answer of a channel, the caller has
     * no way to know how long it is, and a message that is one character too long would turn the
     * booking of a failure into a second failure.
     *
     * @throws PaymentException
     */
    public function markFailed(PaymentTransferEntity $transfer, ?string $failureCode, ?string $failureReason): void
    {
        $settledBefore = $transfer->statusMachineState?->getTechnicalName() === PaymentTransferStates::STATE_SUCCEEDED;

        $this->stateHandler->fail($transfer->id);

        $row = [
            'id' => $transfer->id,
            'failureCode' => $failureCode,
            'failureReason' => $this->boundedReason($failureReason),
        ];

        if ($settledBefore) {
            $row['settledAt'] = null;
        }

        $this->transferRepository->update([$row], Context::createDefaultContext());
    }

    /**
     * The money reached the recipient and came back. Two interfaces do this: alipay refunds a transfer
     * to a bank card when the bank refuses the account (`status=REFUND`, delivered through the same
     * `alipay.fund.trans.order.changed` message), paypal returns a payout nobody claimed within 30 days.
     *
     * The reason is the only free text the row has for it - there is no column of its own.
     *
     * @throws PaymentException
     */
    public function markReturned(PaymentTransferEntity $transfer, ?string $reason = null): void
    {
        $this->stateHandler->markReturned($transfer->id);

        $this->transferRepository->update([[
            'id' => $transfer->id,
            'returnedAt' => $this->clock->now(),
            // cut like the failure reason: it is the raw status of a channel, and the length is not
            // ours to decide - a reason one character too long would turn the booking of the return
            // into a second failure
            'failureReason' => $this->boundedReason($reason),
        ]], Context::createDefaultContext());
    }

    /**
     * The payout will not happen: it was withdrawn before the interface finalized it.
     *
     * The reason is the "reject" of the approval workflow of the administration. It has no column of its
     * own - `metadata.rejectReason` is where the audit trail lives, exactly like the reason of a return
     * lives in `failureReason`. An omitted reason writes nothing, so an existing caller that only wants to
     * abandon a payout keeps its behaviour unchanged.
     *
     * @throws PaymentException
     */
    public function cancel(PaymentTransferEntity $transfer, ?string $reason = null): void
    {
        $this->stateHandler->cancel($transfer->id);

        $row = [
            'id' => $transfer->id,
            'canceledAt' => $this->clock->now(),
        ];

        if ($reason !== null) {
            $row['metadata'] = array_merge(
                $transfer->metadata ?? [],
                // cut like a failure reason: the text comes from an operator and the column is bounded
                ['rejectReason' => $this->boundedReason($reason)]
            );
        }

        $this->transferRepository->update([$row], Context::createDefaultContext());
    }

    /**
     * @throws PaymentException
     */
    public function getById(string $id): PaymentTransferEntity
    {
        $criteria = new Criteria([$id]);
        // the state and the recipient are what every caller needs: the state decides whether an
        // answer may be booked, the recipient is what the payout request is built from
        $criteria->addAssociation('statusMachineState');
        $criteria->addAssociation('recipient');

        $transfer = $this->transferRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$transfer instanceof PaymentTransferEntity) {
            throw PaymentException::transferNotFound($id);
        }

        return $transfer;
    }

    /**
     * The idempotency lookup: the out number identifies the payout of the business case within the app.
     *
     * @throws PaymentException
     */
    public function getByOutNo(PaymentAppEntity $app, string $outNo): ?PaymentTransferEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $app->id),
            new EqualsFilter('outNo', $outNo)
        );
        $criteria->addAssociation('statusMachineState');
        $criteria->addAssociation('recipient');
        $criteria->setLimit(1);

        $transfer = $this->transferRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $transfer instanceof PaymentTransferEntity ? $transfer : null;
    }

    /**
     * The recipient of a transfer, scoped to the app that pays it out.
     *
     * The app is part of the lookup and not a check afterwards: a recipient of another app does not
     * exist for this one, and paying out to it would move money across tenants. That is why the
     * lookup is reused instead of being re-implemented by whoever needs the recipient.
     *
     * @throws PaymentException
     */
    public function recipientOf(PaymentAppEntity $app, PaymentTransferEntity $transfer): PaymentTransferRecipientEntity
    {
        return $this->getRecipient($app, $transfer->recipientId);
    }

    /**
     * Records that the transfer was handed to the channel, without deciding what the attempt means.
     *
     * This exists for the one case where the platform learned nothing: an exception during the
     * submission says the call did not finish, not that the money did not move - the channel may have
     * received and executed the request. Booking a terminal state from it would invite a second
     * payout, so only the audit trail of the attempt is kept and the state stays where it was.
     */
    public function recordSubmitAttempt(PaymentTransferEntity $transfer, string $reason): void
    {
        $this->transferRepository->update([[
            'id' => $transfer->id,
            'submitAttempts' => $transfer->submitAttempts + 1,
            'failureReason' => $this->boundedReason($reason),
        ]], Context::createDefaultContext());
    }

    /**
     * @throws PaymentException
     */
    private function getRecipient(PaymentAppEntity $app, string $recipientId): PaymentTransferRecipientEntity
    {
        $criteria = new Criteria();
        // the app is part of the lookup, not a check afterwards: a recipient of another app does not
        // exist for this one, and paying out to it would move money across tenants
        $criteria->addFilter(
            new EqualsFilter('id', $recipientId),
            new EqualsFilter('paymentAppId', $app->id)
        );
        $criteria->setLimit(1);

        $recipient = $this->recipientRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$recipient instanceof PaymentTransferRecipientEntity) {
            throw PaymentException::transferRecipientNotFound($recipientId);
        }

        return $recipient;
    }

    /**
     * @throws PaymentException
     */
    private function assertCurrencySupported(string $provider, string $currency): void
    {
        if (\in_array($provider, self::CNY_ONLY_PROVIDERS, true) && $currency !== 'CNY') {
            throw PaymentException::currencyNotSupported($provider, $currency);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function confirmPayloadRow(?string $confirmPayload): ?array
    {
        return $confirmPayload === null ? null : ['package_info' => $confirmPayload];
    }

    /**
     * The reason of a failure is channel output: bounded by the column, never by the caller.
     */
    private function boundedReason(?string $reason): ?string
    {
        return $reason === null ? null : mb_substr($reason, 0, self::FAILURE_REASON_LIMIT);
    }

    /**
     * 2 + 12 + 10 = 24 characters, well below the 64 the interfaces allow for a display number.
     */
    private function generateTransferNo(\DateTimeImmutable $now): string
    {
        return 'PT' . $now->format('ymdHis') . strtoupper(bin2hex(random_bytes(5)));
    }
}
