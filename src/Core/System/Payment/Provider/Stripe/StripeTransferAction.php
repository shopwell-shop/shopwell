<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Submits a payout to stripe, which moves the money in two stages:
 *
 *     platform balance --POST /v1/transfers--> connected account --POST /v1/payouts--> the recipient
 *
 * The transfer has no status at all - only `reversed` and `amount_reversed` - so a created transfer
 * never means the recipient has the money: it only means the platform balance paid it into the
 * connected account. The payout is what carries the status machine, and it draws from the balance of
 * the connected account, which is why it rides the `Stripe-Account` header and must not name a
 * `destination` - with one stripe would pay out of the platform balance instead, and it would not
 * even complain about it.
 *
 * The two stages are recorded under `provider_stage_ref` (the `tr_`) and `provider_ref` (the `po_`).
 * A retry after a transfer succeeded but the payout failed resumes at the payout - creating the
 * transfer again would move a second amount out of the platform balance, which is a loss of money
 * the interface would book without any error of its own.
 *
 * The request carries the model the driver prepared with `PaymentGatewayModel::forTransfer()` - the
 * record of the attempt and the {@see TransferResult} live on it, the driver reads them the way it
 * reads the model of a payment order.
 *
 * @internal
 */
#[Package('framework')]
final class StripeTransferAction extends AbstractPaymentAction
{
    final public const STAGE_TRANSFER = 'transfer';

    final public const STAGE_PAYOUT = 'payout';

    final public const IDENTITY_TYPE = 'stripe_account';

    final public const ACCOUNT_ID_PREFIX = 'acct_';

    /**
     * The status of a payout whose money has left the connected account and is on its way to the
     * recipient.
     */
    final public const STATUS_PENDING = 'pending';

    final public const STATUS_IN_TRANSIT = 'in_transit';

    /**
     * Not a terminal answer, although it reads like one: stripe may still return the payout as
     * `failed` within five business days - the webhook and the `markFailed` of the transfer service
     * carry that edge, this action only reports what stripe says right now.
     */
    final public const STATUS_PAID = 'paid';

    final public const STATUS_FAILED = 'failed';

    /**
     * `TransferResult` has no state of its own for a canceled payout - the money never left the
     * connected account and will never, which is what `failed` means for the caller; the reason is
     * kept in the failure code.
     */
    final public const STATUS_CANCELED = 'canceled';

    protected string $apiClass = StripeApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::TRANSFER;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        if (!$request instanceof PaymentTransferRequest) {
            throw RequestNotSupportedException::create($request);
        }

        $api = $this->api();
        $model = $this->modelOf($request);

        $transfer = $request->transfer;

        // stripe refuses every payout of an account that has not finished its onboarding - the call
        // is refused here, so the local state and the idempotency key of the transfer are not burned
        // on a rejection the interface was guaranteed to give
        self::assertRecipientPayable($transfer, $request->recipient);

        $requests = [];
        $responses = [];

        $stageRef = $transfer->providerStageRef;

        if ($stageRef === null || $stageRef === '') {
            $content = self::transferContentOf($transfer, $request->recipient);
            $requests[self::STAGE_TRANSFER] = $content;

            try {
                $answer = $api->createTransfer($content, self::idempotencyKey($transfer->outNo, self::STAGE_TRANSFER));
            } catch (\Throwable $exception) {
                $responses['error'] = $exception->getMessage();
                $this->record($model, $requests, $responses);

                throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
            }

            $responses[self::STAGE_TRANSFER] = $answer;

            if (isset($answer['error'])) {
                // the transfer never opened an object: no money has left the platform balance, so
                // there is no stage reference to keep and the next attempt starts from scratch
                $this->record($model, $requests, $responses);
                $this->setResult($model, new TransferResult(
                    TransferResult::STATE_FAILED,
                    failureCode: self::errorCodeOf($answer) ?? self::STAGE_TRANSFER,
                ));

                return;
            }

            $stageRef = \is_string($answer['id'] ?? null) ? $answer['id'] : null;

            if ($stageRef === null) {
                $this->record($model, $requests, $responses);

                throw PaymentException::gatewayError($api->channel(), 'the channel did not answer a transfer id');
            }
        }

        $content = self::payoutContentOf($transfer);
        $requests[self::STAGE_PAYOUT] = $content;

        try {
            $answer = $api->createPayout(
                $content,
                (string) $request->recipient->providerAccountId,
                self::idempotencyKey($transfer->outNo, self::STAGE_PAYOUT)
            );
        } catch (\Throwable $exception) {
            $responses['error'] = $exception->getMessage();
            $this->record($model, $requests, $responses);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $responses[self::STAGE_PAYOUT] = $answer;

        $this->record($model, $requests, $responses);

        $this->setResult($model, self::resultOf($stageRef, $answer));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentTransferRequest::class);
    }

    /**
     * The recipient must be one stripe pays out to before any request is built: an onboarded
     * connected account with `payouts_enabled` - every stripe payout of an account that is not
     * would be refused, and the refusal must not cost the call of the interface.
     *
     * @throws PaymentException
     */
    public static function assertRecipientPayable(
        PaymentTransferEntity $transfer,
        PaymentTransferRecipientEntity $recipient,
    ): void {
        if ($recipient->payoutEnabled !== true) {
            throw PaymentException::transferRecipientNotReady($recipient->id);
        }

        if ($recipient->identityType !== self::IDENTITY_TYPE) {
            throw PaymentException::transferIdentityNotSupported($transfer->provider, $recipient->identityType);
        }

        $accountId = $recipient->providerAccountId;

        if ($accountId === null || !str_starts_with($accountId, self::ACCOUNT_ID_PREFIX)) {
            throw PaymentException::transferRecipientIncomplete('provider_account_id');
        }
    }

    /**
     * The content of the first stage, `POST /v1/transfers`: the amount moves from the platform
     * balance into the balance of the connected account.
     *
     * The amount is sent exactly as the platform stores it - stripe takes the smallest unit of the
     * currency, zero decimal currencies included, so no conversion happens anywhere on this link.
     *
     * @return array<string, mixed>
     */
    public static function transferContentOf(
        PaymentTransferEntity $transfer,
        PaymentTransferRecipientEntity $recipient,
    ): array {
        return [
            'amount' => $transfer->amount,
            'currency' => strtolower($transfer->currency),
            'destination' => (string) $recipient->providerAccountId,
            'metadata' => ['out_no' => $transfer->outNo],
        ];
    }

    /**
     * The content of the second stage, `POST /v1/payouts` on behalf of the connected account.
     *
     * There is deliberately no `destination`: the call rides the `Stripe-Account` header and pays
     * out of the balance of that account - naming a destination would pay out of the platform
     * balance instead, and stripe would book it without a word.
     *
     * @return array<string, mixed>
     */
    public static function payoutContentOf(PaymentTransferEntity $transfer): array
    {
        return [
            'amount' => $transfer->amount,
            'currency' => strtolower($transfer->currency),
            'metadata' => ['out_no' => $transfer->outNo],
        ];
    }

    /**
     * The answers of the two stages, narrowed to what the payout means.
     *
     * The stage reference is kept on a payout failure on purpose: the transfer has already moved the
     * money into the connected account, and it is the only evidence a support case has that the
     * money sits there. It is also what the next attempt resumes at - the transfer is never created
     * twice.
     *
     * Pure on purpose: the same answers arrive at every retry and what they mean is decided here and
     * nowhere else.
     *
     * @param array<string, mixed> $answer the answer of the payout, the second stage
     */
    public static function resultOf(string $stageRef, array $answer): TransferResult
    {
        if (isset($answer['error'])) {
            // the payout was rejected, the transfer stands: the money is on the connected account
            return new TransferResult(
                TransferResult::STATE_FAILED,
                providerStageRef: $stageRef,
                failureCode: self::errorCodeOf($answer) ?? self::STAGE_PAYOUT,
            );
        }

        $status = self::stringOf($answer, 'status');
        $state = self::stateOf($status);

        return new TransferResult(
            $state,
            providerRef: self::stringOf($answer, 'id') ?: null,
            providerStageRef: $stageRef,
            failureCode: $state === TransferResult::STATE_FAILED ? self::failureCodeOf($status, $answer) : null,
        );
    }

    /**
     * The status of a payout, mapped to what the platform does with the transfer.
     *
     * `paid` maps to `succeeded` although it is not final: stripe may still return the payout as
     * `failed` within five business days, and the webhook plus the `markFailed` of the transfer
     * service carry that edge - this mapping only reports what stripe says right now, it does not
     * downgrade a `paid` to be on the safe side, because the interface already said the money
     * arrived.
     */
    public static function stateOf(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => TransferResult::STATE_SUCCEEDED,
            self::STATUS_PENDING, self::STATUS_IN_TRANSIT => TransferResult::STATE_PROCESSING,
            self::STATUS_FAILED, self::STATUS_CANCELED => TransferResult::STATE_FAILED,
            default => TransferResult::STATE_UNKNOWN,
        };
    }

    /**
     * The idempotency key of one stage of a payout - the out number of the transfer names the
     * business case, the stage keeps the two calls apart.
     */
    public static function idempotencyKey(string $outNo, string $stage): string
    {
        return $outNo . ':' . $stage;
    }

    /**
     * The failure code of a payout that will never arrive.
     *
     * A canceled payout has no failure code of its own - the cancellation is the reason, so it is
     * named the way stripe names it.
     *
     * @param array<string, mixed> $answer
     */
    public static function failureCodeOf(string $status, array $answer): string
    {
        if ($status === self::STATUS_CANCELED) {
            return self::STATUS_CANCELED;
        }

        return self::stringOf($answer, 'failure_code') ?: $status;
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }

    /**
     * @param array<string, mixed> $requests
     * @param array<string, mixed> $responses
     */
    private function record(PaymentGatewayModel $model, array $requests, array $responses): void
    {
        $this->recordRequest($model, $requests);
        $this->recordResponse($model, $responses);
    }

    /**
     * The code of a rejection of the interface, or null when the answer carries none.
     *
     * @param array<string, mixed> $answer
     */
    private static function errorCodeOf(array $answer): ?string
    {
        $error = \is_array($answer['error'] ?? null) ? $answer['error'] : [];

        return \is_string($error['code'] ?? null) && $error['code'] !== '' ? $error['code'] : null;
    }

    /**
     * @param array<string, mixed> $answer
     */
    private static function stringOf(array $answer, string $key): string
    {
        $value = $answer[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }
}
