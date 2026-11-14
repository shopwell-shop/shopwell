<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferResultBookerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testTwoStaleCallersCanClaimOnlyOneAttempt(): void
    {
        $transfer = $this->createTransfer();
        $booker = $this->booker();
        $booker->claim($transfer);

        static::assertSame(1, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
        static::assertSame(0, $transfer->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));

        $booker->claim($transfer);
    }

    public function testAStaleUnclaimedEntityCannotCancelAClaimedAttempt(): void
    {
        $transfer = $this->createTransfer();
        $booker = $this->booker();
        $booker->claim($transfer);

        try {
            $booker->cancel($transfer, 'operator canceled from a stale page');
            static::fail('An attempt that may have reached the channel cannot be canceled locally.');
        } catch (PaymentTransferException $exception) {
            static::assertSame(PaymentTransferException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
        }

        $fresh = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertSame(1, $fresh->submitAttempts);
        static::assertNull($fresh->canceledAt);
        static::assertSame(PaymentTransferStates::STATE_CREATED, $fresh->statusMachineState?->getTechnicalName());
    }

    public function testACanceledPayoutCannotBeClaimedThroughAStaleEntity(): void
    {
        $transfer = $this->createTransfer();
        $booker = $this->booker();
        $booker->cancel($transfer, 'operator rejected');

        $fresh = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertNotNull($fresh->canceledAt);
        static::assertSame(0, $fresh->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));

        $booker->claim($transfer);
    }

    private function createTransfer(): PaymentTransferEntity
    {
        return PaymentTransferApprovalFixture::create(static::getContainer());
    }

    private function booker(): PaymentTransferResultBooker
    {
        $approval = static::createStub(PaymentTransferApprovalService::class);
        $approval->method('assertApproved')->willReturn(new PaymentTransferRecipientEntity());

        return new PaymentTransferResultBooker(static::getContainer()->get(PaymentTransferService::class), static::getContainer()->get(Connection::class), $approval);
    }
}
