<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;

/**
 * Hands a payout over to its channel - the submitting counterpart of the capture of a payment order.
 *
 * The service behind it decides which state an answer means and keeps the bookkeeping; this class is
 * the side that talks to the interface: it resolves the credentials of the app, builds the gateway of
 * the channel, executes the payout and books the answer. A notification or a query answers what
 * happens afterwards - the submitter only knows what the channel said to the call itself.
 *
 * Every guard runs before the gateway is built: submitting is the one operation that moves money out.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferSubmitter
{
    final public const STEP_SUBMIT = 'submit';
    final public const STEP_PROCESS = 'process';
    final public const STEP_SUCCEED = 'succeed';
    final public const STEP_AWAIT_CONFIRM = 'await_confirm';
    final public const STEP_FAIL = 'fail';

    public function __construct(
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentTransferService $transferService,
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
        // the credentials are resolved from the app, so a transfer of another app would be paid out of
        // this merchant account - the check is about money, not about form
        if ($transfer->paymentAppId !== $app->id) {
            throw PaymentException::transferForeignApp($app->id, $transfer->outNo);
        }

        $state = $transfer->statusMachineState?->getTechnicalName();

        // submitting twice is not a retry but a second payout: a retry always queries the interface
        // first. A transfer whose state was not read is refused as well - the caller has to load it
        // with its state, and sending what can not be classified is the one thing that must not happen
        if ($state !== PaymentTransferStates::STATE_CREATED) {
            throw PaymentException::transferAlreadyFinalized($transfer->outNo, $state ?? 'unknown');
        }

        $recipient = $this->transferService->recipientOf($app, $transfer);

        // a payout has no device, and null means "no restriction" - a channel that brings no device
        // passes as well, so this is exactly the "no device" of a payout and not an omission
        $resolved = $this->configResolver->resolve(
            $app,
            $transfer->channel,
            $transfer->currency,
            null,
            $transfer->channelParams ?? []
        );

        $model = PaymentGatewayModel::forTransfer($transfer);

        // An interface that never declared a payout cannot be asked to pay out. Without the guard
        // payum fails inside the gateway with "no action supports this request", which the catch below
        // can only report as a channel fault - and a 502 on money leaving the platform is the one
        // answer that makes a caller retry a request that can never succeed. The guard sits before the
        // attempt is recorded: nothing was attempted, so nothing may be written as an attempt.
        if (!$resolved->provider->supports(PaymentCapability::TRANSFER)) {
            throw PaymentException::providerLacksCapability($resolved->provider->technicalName, PaymentCapability::TRANSFER->value);
        }

        // no transfer action reads the notification or the return url - those two exist for the
        // capture and the refund of a payment order only. They are passed empty on purpose: a payout
        // has no payer to send anywhere, and wiring a real url here would be a callback nobody serves
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, '', '');

        try {
            $gateway->execute(new PaymentTransferRequest($model, $transfer, $recipient, $transfer->channelParams ?? []));
            $result = $model->result(TransferResult::class, $transfer->channel);
        } catch (\Throwable $exception) {
            // the money may have moved - never book a terminal state from a call that did not answer.
            // This runs for every failure, including a configuration error that never left the
            // platform: "someone tried and this is why it failed" is true either way, and the attempt
            // counter carries no behaviour - it is an audit trail
            $this->transferService->recordSubmitAttempt($transfer, $exception->getMessage());

            if ($exception instanceof PaymentException) {
                throw $exception;
            }

            throw PaymentException::gatewayError($transfer->channel, $exception->getMessage(), $exception);
        }

        $this->apply($model, $transfer, $result);

        return $result;
    }

    /**
     * The service calls the answer requires, in the order the state machine allows them.
     *
     * Pure on purpose: {@see PaymentTransferService} is final and can not be doubled, so the
     * money-critical knowledge of what an answer means has to be assertable without a database and
     * without a mock, and {@see apply()} only dispatches what this answers.
     *
     * A `succeeded` answer is booked through `process` because the machine has no
     * `submitted -> succeeded` shortcut to rely on: `submit -> process -> succeed` is the money path,
     * and each step is a real observation the call made.
     *
     * A `returned` answer at submission time is a `failed`, not a `mark_returned`: a transfer that
     * never left `created` can not have arrived and come back, and a channel answering `RETURNED` on
     * a fresh payout has simply not moved the money. `mark_returned` is reserved for the reconciler,
     * which observes the transfer on its way first.
     *
     * A `failed` answer without an object reference is a refusal that never opened anything at the
     * interface - there is nothing to submit, and `created -> failed` is the legal edge for it.
     *
     * @return list<string>
     */
    public static function stepNames(TransferResult $result): array
    {
        $hasRef = $result->providerRef !== null && $result->providerRef !== '';

        return match ($result->state) {
            TransferResult::STATE_SUCCEEDED => [self::STEP_SUBMIT, self::STEP_PROCESS, self::STEP_SUCCEED],
            TransferResult::STATE_AWAITING_CONFIRM => [self::STEP_SUBMIT, self::STEP_AWAIT_CONFIRM],
            TransferResult::STATE_PROCESSING, TransferResult::STATE_ACCEPTED => [self::STEP_SUBMIT, self::STEP_PROCESS],
            TransferResult::STATE_FAILED => $hasRef ? [self::STEP_SUBMIT, self::STEP_FAIL] : [self::STEP_FAIL],
            TransferResult::STATE_RETURNED => [self::STEP_SUBMIT, self::STEP_PROCESS, self::STEP_FAIL],
            TransferResult::STATE_UNKNOWN => $hasRef ? [self::STEP_SUBMIT] : [],
            // an answer the platform does not know is treated like an answer it did not get: nothing
            // is booked and the query of the channel decides
            default => [],
        };
    }

    /**
     * @throws PaymentException
     */
    private function apply(PaymentGatewayModel $model, PaymentTransferEntity $transfer, TransferResult $result): void
    {
        $this->assertObjectReference($transfer, $result);

        foreach (self::stepNames($result) as $step) {
            switch ($step) {
                case self::STEP_SUBMIT:
                    $this->transferService->markSubmitted(
                        $transfer,
                        (string) $result->providerRef,
                        $result->providerStageRef,
                        $result->confirmPayload,
                    );

                    break;

                case self::STEP_PROCESS:
                    $this->transferService->markProcessing($transfer);

                    break;

                case self::STEP_SUCCEED:
                    $this->transferService->markSucceeded($transfer);

                    break;

                case self::STEP_AWAIT_CONFIRM:
                    $this->transferService->markAwaitingConfirm($transfer, $result->confirmPayload);

                    break;

                case self::STEP_FAIL:
                    // the code of the interface is the evidence, and `returned` carries none - without
                    // the fallback the row would say nothing at all. The reason is the raw answer: for
                    // alipay the sub message, for stripe the failure message, and for the rest whatever
                    // the channel chose to explain
                    $this->transferService->markFailed(
                        $transfer,
                        $result->failureCode ?? $result->state,
                        self::reasonOf($model),
                    );

                    break;
            }
        }
    }

    /**
     * The answer of the channel as the row keeps it.
     *
     * The response is recorded by the action before it throws or returns, so this is the same evidence
     * a support case is debugged with. `null` when the action answered nothing at all - an empty JSON
     * object would claim an answer that does not exist.
     */
    private static function reasonOf(PaymentGatewayModel $model): ?string
    {
        $response = $model->response();

        if ($response === []) {
            return null;
        }

        // the wire content of a channel is not guaranteed to be valid utf-8, so a failing encode must
        // not abort the booking - the failure is worth more without its reason than not at all
        $reason = json_encode($response, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $reason === false ? null : $reason;
    }

    /**
     * A channel that answers success or processing without naming the object it created violates its
     * contract, and booking it would produce a transfer nobody can ever query - the money would be
     * untraceable. `failed` is exempt: a refusal that never opened an object has no reference.
     *
     * @throws PaymentException
     */
    private function assertObjectReference(PaymentTransferEntity $transfer, TransferResult $result): void
    {
        if ($result->state === TransferResult::STATE_UNKNOWN || $result->state === TransferResult::STATE_FAILED) {
            return;
        }

        if ($result->providerRef !== null && $result->providerRef !== '') {
            return;
        }

        throw PaymentException::gatewayError(
            $transfer->channel,
            \sprintf('the channel answered %s without an object reference', $result->state)
        );
    }
}
