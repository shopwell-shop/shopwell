<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Books the answer of a transfer submission as one atomic sequence of state transitions.
 *
 * The channel call has already finished when this collaborator runs: no external I/O belongs to its
 * transaction. All writes use the default connection shared by the transfer and state machine writers.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferResultBooker
{
    final public const STEP_SUBMIT = 'submit';
    final public const STEP_PROCESS = 'process';
    final public const STEP_SUCCEED = 'succeed';
    final public const STEP_AWAIT_CONFIRM = 'await_confirm';
    final public const STEP_FAIL = 'fail';

    public function __construct(
        private readonly PaymentTransferService $transferService,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Claims the first attempt before external I/O. A stale caller or a crashed worker cannot send
     * again: the single conditional write is committed before the remote request starts.
     */
    public function claim(PaymentTransferEntity $transfer): void
    {
        $claimed = $this->connection->executeStatement(
            'UPDATE payment_transfer SET submit_attempts = 1, updated_at = UTC_TIMESTAMP(3)
             WHERE id = :id AND status_id = :statusId AND submit_attempts = 0',
            ['id' => Uuid::fromHexToBytes($transfer->id), 'statusId' => Uuid::fromHexToBytes($transfer->statusId)],
            ['id' => ParameterType::BINARY, 'statusId' => ParameterType::BINARY]
        );

        if ($claimed !== 1) {
            throw PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown');
        }
    }

    /**
     * A local cancellation and the first remote attempt compete for the same row. The lock lasts
     * only through the state write, so a stale unclaimed entity cannot cancel a payout in flight.
     */
    public function cancel(PaymentTransferEntity $transfer, ?string $reason = null): void
    {
        RetryableWriteTransaction::retryable($this->connection, function () use ($transfer, $reason): void {
            $row = $this->connection->fetchAssociative(
                'SELECT submit_attempts, status_id FROM payment_transfer WHERE id = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($transfer->id)],
                ['id' => ParameterType::BINARY]
            );

            if ($row === false) {
                throw PaymentTransferException::transferNotFound($transfer->id);
            }

            if ((int) $row['submit_attempts'] !== 0 || Uuid::fromBytesToHex((string) $row['status_id']) !== $transfer->statusId) {
                throw PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown');
            }

            $this->transferService->cancel($transfer, $reason);
        });
    }

    /**
     * The service calls the answer requires, in the order the state machine allows them.
     *
     * A `succeeded` answer is booked through `process` because the machine has no
     * `submitted -> succeeded` shortcut: `submit -> process -> succeed` is the money path.
     *
     * A `returned` answer at submission time is a failure: a transfer that never left `created` cannot
     * have arrived and come back. A failure without an object reference never opened anything at the
     * interface, so it takes the legal `created -> failed` edge without a submission.
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
            default => [],
        };
    }

    /**
     * @throws PaymentException
     */
    public function apply(PaymentGatewayModel $model, PaymentTransferEntity $transfer, TransferResult $result): void
    {
        $this->assertObjectReference($transfer, $result);

        $steps = self::stepNames($result);
        if ($steps === []) {
            return;
        }

        $apply = function () use ($model, $transfer, $result, $steps): void {
            foreach ($steps as $step) {
                $this->applyStep($model, $transfer, $result, $step);
            }
        };

        RetryableWriteTransaction::retryable($this->connection, $apply);
    }

    /**
     * @throws PaymentException
     */
    private function applyStep(PaymentGatewayModel $model, PaymentTransferEntity $transfer, TransferResult $result, string $step): void
    {
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
                $this->transferService->markFailed(
                    $transfer,
                    $result->failureCode ?? $result->state,
                    self::reasonOf($model),
                );

                break;
        }
    }

    /**
     * The raw response is the evidence support needs; an empty response stays `null` rather than
     * claiming that an empty JSON object arrived. Invalid UTF-8 must not abort the failure booking.
     */
    private static function reasonOf(PaymentGatewayModel $model): ?string
    {
        $response = $model->response();

        if ($response === []) {
            return null;
        }

        $reason = json_encode($response, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $reason === false ? null : $reason;
    }

    /**
     * Success and processing answers must name the object they created, otherwise the transfer could
     * never be queried. A refusal without an object is valid and needs no reference.
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

        throw PaymentGatewayException::gatewayError(
            $transfer->channel,
            \sprintf('the channel answered %s without an object reference', $result->state)
        );
    }
}
