<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Payum\Core\GatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;

/**
 * Hands a payout over to its channel and coordinates auditing and booking the answer.
 *
 * Guards, the channel call, the attempt audit and result booking are separate boundaries. In
 * particular, the channel call always finishes before the result booker opens its write transaction.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferSubmitter
{
    // Compatibility for the reconciler and existing callers; submission steps belong to the result booker.
    final public const STEP_SUBMIT = PaymentTransferResultBooker::STEP_SUBMIT;
    final public const STEP_PROCESS = PaymentTransferResultBooker::STEP_PROCESS;
    final public const STEP_SUCCEED = PaymentTransferResultBooker::STEP_SUCCEED;
    final public const STEP_AWAIT_CONFIRM = PaymentTransferResultBooker::STEP_AWAIT_CONFIRM;
    final public const STEP_FAIL = PaymentTransferResultBooker::STEP_FAIL;

    public function __construct(
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentTransferService $transferService,
        private readonly PaymentTransferResultBooker $resultBooker,
    ) {
    }

    /**
     * Submits a payout in `created` and books the answer of the channel.
     *
     * A thrown exception is deliberately not a failure. It says the platform never learned the
     * outcome - the channel may have received and executed the request - so nothing terminal is
     * booked; only the attempt is recorded and the transfer stays `created`. Booking `failed` there
     * would invite a second payout, and leaving the transfer where it is lets the caller send it
     * again, which is safe because all four interfaces are idempotent on our business number:
     * alipay's documented recovery for `SYSTEM_ERROR` is re-sending the same `out_biz_no`, wechat
     * demands "never change the number, retry with the original parameters", stripe honours
     * `Idempotency-Key` for at least 24 hours, and paypal honours `PayPal-Request-Id` and
     * `sender_batch_id` for 30 days.
     *
     * A domain error is not wrapped: it names its own cause and its own status, and wrapping it would
     * report a missing parameter as a gateway outage and turn a 4xx into a 5xx the caller believes it
     * may retry. The transfer stays `created` either way - "the scene is not configured" and "the
     * channel timed out" are the same thing for the money: neither says that it did not move.
     *
     * @throws PaymentException
     */
    public function submit(PaymentAppEntity $app, PaymentTransferEntity $transfer): TransferResult
    {
        $this->assertSubmittable($app, $transfer);
        [$gateway, $model, $request] = $this->prepareChannel($app, $transfer);

        try {
            $result = $this->callChannel($gateway, $model, $request, $transfer->channel);
        } catch (\Throwable $exception) {
            // The money may have moved - never book a terminal state from a call that did not answer.
            $this->transferService->recordSubmitAttempt($transfer, $exception->getMessage());

            if ($exception instanceof PaymentException) {
                throw $exception;
            }

            throw PaymentException::gatewayError($transfer->channel, $exception->getMessage(), $exception);
        }

        try {
            $this->resultBooker->apply($model, $transfer, $result);
        } catch (\Throwable $exception) {
            // The channel answered, so the request demonstrably reached it. An answer that cannot be
            // booked is "the platform does not know", never "provably nothing happened".
            $this->recordUnbookedAnswer($transfer, $exception);

            if ($exception instanceof PaymentException) {
                throw $exception;
            }

            throw PaymentException::gatewayError($transfer->channel, $exception->getMessage(), $exception);
        }

        return $result;
    }

    /**
     * Compatibility entry for existing callers; the mapping is owned by the result booker.
     *
     * @return list<string>
     */
    public static function stepNames(TransferResult $result): array
    {
        return PaymentTransferResultBooker::stepNames($result);
    }

    /**
     * Every guard that does not need the resolved provider runs before credentials or a gateway are
     * touched: submitting is the one operation that moves money out.
     *
     * @throws PaymentException
     */
    private function assertSubmittable(PaymentAppEntity $app, PaymentTransferEntity $transfer): void
    {
        // Credentials are resolved from the app, so a transfer of another app would be paid out of
        // this merchant account.
        if ($transfer->paymentAppId !== $app->id) {
            throw PaymentException::transferForeignApp($app->id, $transfer->outNo);
        }

        $state = $transfer->statusMachineState?->getTechnicalName();

        // Submitting twice is a second payout, not a retry. An unread state fails closed as well.
        if ($state !== PaymentTransferStates::STATE_CREATED) {
            throw PaymentException::transferAlreadyFinalized($transfer->outNo, $state ?? 'unknown');
        }
    }

    /**
     * Resolves the channel and checks its payout capability before an attempt can be audited.
     *
     * @throws PaymentException
     *
     * @return array{GatewayInterface, PaymentGatewayModel, PaymentTransferRequest}
     */
    private function prepareChannel(PaymentAppEntity $app, PaymentTransferEntity $transfer): array
    {
        $recipient = $this->transferService->recipientOf($app, $transfer);

        // A payout has no device, and null means "no restriction".
        $resolved = $this->configResolver->resolve(
            $app,
            $transfer->channel,
            $transfer->currency,
            null,
            $transfer->channelParams ?? []
        );

        $model = PaymentGatewayModel::forTransfer($transfer);

        // This guard precedes both the gateway call and the attempt audit: nothing was attempted.
        if (!$resolved->provider->supports(PaymentCapability::TRANSFER)) {
            throw PaymentException::providerLacksCapability($resolved->provider->technicalName, PaymentCapability::TRANSFER->value);
        }

        // Notification and return URLs belong to capture and refund only; a payout has no payer.
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, '', '');
        $request = new PaymentTransferRequest($model, $transfer, $recipient, $transfer->channelParams ?? []);

        return [$gateway, $model, $request];
    }

    /**
     * Performs the external call. This is deliberately outside every database transaction.
     */
    private function callChannel(
        GatewayInterface $gateway,
        PaymentGatewayModel $model,
        PaymentTransferRequest $request,
        string $channel,
    ): TransferResult {
        $gateway->execute($request);

        return $model->result(TransferResult::class, $channel);
    }

    /**
     * Records the attempt of an answer that arrived but could not be booked.
     *
     * The row is read again before it is written, and the write happens only while it is still
     * `created`. A row that already moved has its attempt evidence from `markSubmitted()` and must not
     * be counted a second time from the stale object of the caller.
     *
     * If the row cannot be read back, the in-memory value is the safer fallback: over-counting can only
     * turn a rejection into an unknown, never the other way round.
     */
    private function recordUnbookedAnswer(PaymentTransferEntity $transfer, \Throwable $exception): void
    {
        $reason = $exception->getMessage();

        try {
            $fresh = $this->transferService->getById($transfer->id);
        } catch (\Throwable) {
            $this->transferService->recordSubmitAttempt($transfer, $reason);

            return;
        }

        if ($fresh->statusMachineState?->getTechnicalName() !== PaymentTransferStates::STATE_CREATED) {
            return;
        }

        $this->transferService->recordSubmitAttempt($fresh, $reason);
    }
}
