<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;

/**
 * @internal
 */
#[Package('framework')]
class PaymentOrderRowLockTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testLockOrderReadsTheAmountsOfTheLockedRow(): void
    {
        $orderId = $this->createOrder(paidAmount: 1000, refundedAmount: 300);
        $rowLock = static::getContainer()->get(PaymentOrderRowLock::class);

        static::assertSame(
            ['paidAmount' => 1000, 'refundedAmount' => 300],
            $rowLock->transaction(static fn (): ?array => $rowLock->lockOrder($orderId))
        );
        static::assertNull($rowLock->transaction(static fn (): ?array => $rowLock->lockOrder(Uuid::randomHex())));
    }

    public function testLockRefundReadsWhetherTheLockedRefundWasSettled(): void
    {
        $orderId = $this->createOrder();
        $open = $this->createRefund($orderId, PaymentRefundStates::STATE_OPEN, 300);
        $settled = $this->createRefund($orderId, PaymentRefundStates::STATE_FINISHED, 400, new \DateTimeImmutable());
        $rowLock = static::getContainer()->get(PaymentOrderRowLock::class);

        static::assertFalse($rowLock->transaction(static fn (): ?bool => $rowLock->lockRefund($open)));
        static::assertTrue($rowLock->transaction(static fn (): ?bool => $rowLock->lockRefund($settled)));
        static::assertNull($rowLock->transaction(static fn (): ?bool => $rowLock->lockRefund(Uuid::randomHex())));
    }

    public function testReservedAmountExcludesFailedAndSettledRefunds(): void
    {
        $orderId = $this->createOrder();
        $this->createRefund($orderId, PaymentRefundStates::STATE_OPEN, 300);
        $this->createRefund($orderId, PaymentRefundStates::STATE_PROCESSING, 200);
        $this->createRefund($orderId, PaymentRefundStates::STATE_FAILED, 500);
        $this->createRefund($orderId, PaymentRefundStates::STATE_FINISHED, 400, new \DateTimeImmutable());

        $rowLock = static::getContainer()->get(PaymentOrderRowLock::class);

        static::assertSame(500, $rowLock->reservedRefundAmount($orderId));
    }

    private function createOrder(int $paidAmount = 1000, int $refundedAmount = 0): string
    {
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();

        /** @var EntityRepository<PaymentAppCollection> $appRepository */
        $appRepository = static::getContainer()->get('payment_app.repository');
        $appRepository->create([[
            'id' => $appId,
            'technicalName' => 'row_lock_' . bin2hex(random_bytes(6)),
            'name' => 'Payment row lock test app',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'appSecret' => static::getContainer()->get(PaymentCredentialCipher::class)->encryptValue('secret'),
            'active' => true,
        ]], $context);

        $orderId = Uuid::randomHex();

        /** @var EntityRepository<PaymentOrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('payment_order.repository');
        $orderRepository->create([[
            'id' => $orderId,
            'paymentNo' => 'PG' . strtoupper(bin2hex(random_bytes(8))),
            'outTradeNo' => 'OT' . strtoupper(bin2hex(random_bytes(8))),
            'paymentAppId' => $appId,
            'amount' => 1000,
            'paidAmount' => $paidAmount,
            'refundedAmount' => $refundedAmount,
            'currency' => 'CNY',
            'subject' => 'Payment row lock test',
            'stateId' => $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_PAID),
            'paidAt' => new \DateTimeImmutable(),
        ]], $context);

        return $orderId;
    }

    private function createRefund(
        string $orderId,
        string $state,
        int $amount,
        ?\DateTimeInterface $refundedAt = null
    ): string {
        $refundId = Uuid::randomHex();

        /** @var EntityRepository<PaymentRefundCollection> $refundRepository */
        $refundRepository = static::getContainer()->get('payment_refund.repository');
        $refundRepository->create([[
            'id' => $refundId,
            'refundNo' => 'PR' . strtoupper(bin2hex(random_bytes(8))),
            'paymentOrderId' => $orderId,
            'outRefundNo' => 'OR' . strtoupper(bin2hex(random_bytes(8))),
            'amount' => $amount,
            'currency' => 'CNY',
            'stateId' => $this->stateId(PaymentRefundStates::STATE_MACHINE, $state),
            'refundedAt' => $refundedAt,
        ]], Context::createDefaultContext());

        return $refundId;
    }

    private function stateId(string $machine, string $state): string
    {
        $id = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(state.id)) FROM state_machine_state state
             INNER JOIN state_machine machine ON machine.id = state.state_machine_id
             WHERE machine.technical_name = :machine AND state.technical_name = :state',
            ['machine' => $machine, 'state' => $state]
        );

        static::assertIsString($id);

        return $id;
    }
}
