<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\OrderReturn\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundExecutor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderReturnRefundExecutorTest extends TestCase
{
    private Connection $connection;

    private OrderReturnRefundExecutor $executor;

    private OrderReturnEntity $orderReturn;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE order_return (id BLOB PRIMARY KEY, state_id BLOB, refund_destination TEXT)');
        $this->connection->executeStatement('CREATE TABLE state_machine_state (id BLOB PRIMARY KEY, technical_name TEXT)');
        $this->connection->executeStatement('INSERT INTO state_machine_state VALUES (\'open\', \'open\')');
        $this->orderReturn = (new OrderReturnEntity())->assign([
            'id' => Uuid::randomHex(), 'orderId' => Uuid::randomHex(), 'returnNumber' => 'RT-1',
            'refundDestination' => OrderReturnEntity::REFUND_WALLET,
        ]);
        $this->connection->insert('order_return', [
            'id' => Uuid::fromHexToBytes($this->orderReturn->id), 'state_id' => 'open',
        ], ['id' => ParameterType::BINARY]);
        $this->executor = new OrderReturnRefundExecutor($this->connection, new LockFactory(new InMemoryStore()), static::createStub(PaymentOrderService::class), static::createStub(PaymentOrderRowLock::class));
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testTheFirstDestinationCannotBeOverwrittenByAnotherRequest(): void
    {
        static::assertTrue($this->executor->claimDestination($this->orderReturn->id, 'wallet'));
        static::assertFalse($this->executor->claimDestination($this->orderReturn->id, 'original'));
        static::assertTrue($this->executor->claimDestination($this->orderReturn->id, 'wallet'));
    }

    public function testDifferentReturnsOfTheSameOrderCannotAllocateFundsTogether(): void
    {
        $other = clone $this->orderReturn;
        $other->id = Uuid::randomHex();
        $other->returnNumber = 'RT-2';
        $other->refundDestination = OrderReturnEntity::REFUND_ORIGINAL;

        $this->expectExceptionObject(OrderReturnException::refundNotPossible('RT-2', 'another refund of this order is in progress.'));
        $this->executor->run($this->orderReturn, new OrderEntity(), function () use ($other): void {
            $this->executor->run($other, new OrderEntity(), static function (): void {
                static::fail('A concurrent payout must not allocate funds.');
            });
        });
    }

    public function testFailedWalletPayoutRollsBackAllLocalSourcesAndReleasesTheLock(): void
    {
        try {
            $this->executor->run($this->orderReturn, new OrderEntity(), function (): void {
                $this->connection->executeStatement('UPDATE state_machine_state SET technical_name = \'closed\'');
                throw new \RuntimeException('wallet payout failed after points were credited');
            });
            static::fail('The payout must fail.');
        } catch (\RuntimeException $exception) {
            static::assertSame('wallet payout failed after points were credited', $exception->getMessage());
        }

        static::assertSame('open', $this->connection->fetchOne('SELECT technical_name FROM state_machine_state'));
        $executed = false;
        $this->executor->run($this->orderReturn, new OrderEntity(), static function () use (&$executed): void { $executed = true; });
        static::assertTrue($executed);
    }

    public function testPaymentIsLockedBeforeSourcesReadTheirBalances(): void
    {
        $payment = (new PaymentOrderEntity())->assign(['id' => Uuid::randomHex()]);
        $payments = static::createStub(PaymentOrderService::class);
        $payments->method('findLatestByOrderTransaction')->willReturn($payment);
        $locked = false;
        $paymentLock = $this->createMock(PaymentOrderRowLock::class);
        $paymentLock->expects($this->once())->method('lockOrder')->with($payment->id)
            ->willReturnCallback(static function () use (&$locked): array {
                $locked = true;

                return ['paidAmount' => 8000, 'refundedAmount' => 0];
            });
        $executor = new OrderReturnRefundExecutor($this->connection, new LockFactory(new InMemoryStore()), $payments, $paymentLock);
        $order = (new OrderEntity())->assign(['primaryOrderTransactionId' => Uuid::randomHex()]);

        $executor->run($this->orderReturn, $order, static function () use (&$locked): void {
            static::assertTrue($locked);
        });
    }

    public function testMissingPaymentPreventsAnySourcePayout(): void
    {
        $payment = (new PaymentOrderEntity())->assign(['id' => Uuid::randomHex()]);
        $payments = static::createStub(PaymentOrderService::class);
        $payments->method('findLatestByOrderTransaction')->willReturn($payment);
        $paymentLock = static::createStub(PaymentOrderRowLock::class);
        $paymentLock->method('lockOrder')->willReturn(null);
        $executor = new OrderReturnRefundExecutor($this->connection, new LockFactory(new InMemoryStore()), $payments, $paymentLock);
        $order = (new OrderEntity())->assign(['primaryOrderTransactionId' => Uuid::randomHex()]);

        $this->expectExceptionObject(OrderReturnException::refundNotPossible('RT-1', 'the collected payment no longer exists.'));
        $executor->run($this->orderReturn, $order, static function (): void {
            static::fail('No source may pay without the payment lock.');
        });
    }

    public function testACompletedReturnDoesNotPayOutAgain(): void
    {
        $this->connection->executeStatement('UPDATE state_machine_state SET technical_name = \'closed\'');
        $this->executor->run($this->orderReturn, new OrderEntity(), static function (): void {
            static::fail('A completed return must not be paid again.');
        });
        static::assertSame('closed', $this->connection->fetchOne('SELECT technical_name FROM state_machine_state'));
    }
}
