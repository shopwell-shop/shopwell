<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferResultBooker::class)]
class PaymentTransferResultBookerTest extends TestCase
{
    public function testASucceededAnswerWalksTheWholeMoneyPath(): void
    {
        static::assertSame(
            [
                PaymentTransferResultBooker::STEP_SUBMIT,
                PaymentTransferResultBooker::STEP_PROCESS,
                PaymentTransferResultBooker::STEP_SUCCEED,
            ],
            PaymentTransferResultBooker::stepNames(
                new TransferResult(TransferResult::STATE_SUCCEEDED, 'po_1')
            )
        );
    }

    public function testAnAwaitingConfirmAnswerOnlyWaits(): void
    {
        static::assertSame(
            [PaymentTransferResultBooker::STEP_SUBMIT, PaymentTransferResultBooker::STEP_AWAIT_CONFIRM],
            PaymentTransferResultBooker::stepNames(
                new TransferResult(TransferResult::STATE_AWAITING_CONFIRM, 'transfer-bill-1', confirmPayload: 'MchId=1')
            )
        );
    }

    #[DataProvider('movingStates')]
    public function testAMovingAnswerIsSubmittedAndProcessing(string $state): void
    {
        static::assertSame(
            [PaymentTransferResultBooker::STEP_SUBMIT, PaymentTransferResultBooker::STEP_PROCESS],
            PaymentTransferResultBooker::stepNames(new TransferResult($state, 'order-1'))
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function movingStates(): iterable
    {
        yield 'processing' => [TransferResult::STATE_PROCESSING];
        yield 'accepted' => [TransferResult::STATE_ACCEPTED];
    }

    public function testAFailureWithAnObjectIsSubmittedFirst(): void
    {
        static::assertSame(
            [PaymentTransferResultBooker::STEP_SUBMIT, PaymentTransferResultBooker::STEP_FAIL],
            PaymentTransferResultBooker::stepNames(
                new TransferResult(TransferResult::STATE_FAILED, 'order-1', failureCode: 'PAYEE_NOT_EXIST')
            )
        );
    }

    public function testAFailureWithoutAnObjectIsAFailureFromCreated(): void
    {
        static::assertSame(
            [PaymentTransferResultBooker::STEP_FAIL],
            PaymentTransferResultBooker::stepNames(
                new TransferResult(TransferResult::STATE_FAILED, failureCode: 'PAYEE_NOT_EXIST')
            )
        );
    }

    public function testAReturnedAnswerAtSubmissionIsAFailureNotAReturn(): void
    {
        $steps = PaymentTransferResultBooker::stepNames(new TransferResult(TransferResult::STATE_RETURNED, 'order-1'));

        static::assertSame(
            [
                PaymentTransferResultBooker::STEP_SUBMIT,
                PaymentTransferResultBooker::STEP_PROCESS,
                PaymentTransferResultBooker::STEP_FAIL,
            ],
            $steps
        );
        static::assertNotContains(PaymentTransferReconciler::STEP_MARK_RETURNED, $steps);
    }

    public function testAnUnknownAnswerWithAnObjectOnlyRecordsTheSubmission(): void
    {
        static::assertSame(
            [PaymentTransferResultBooker::STEP_SUBMIT],
            PaymentTransferResultBooker::stepNames(new TransferResult(TransferResult::STATE_UNKNOWN, 'order-1'))
        );
    }

    public function testAnUnknownAnswerWithoutAnObjectRecordsNothing(): void
    {
        static::assertSame([], PaymentTransferResultBooker::stepNames(new TransferResult(TransferResult::STATE_UNKNOWN)));
    }
}
