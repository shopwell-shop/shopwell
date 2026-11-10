<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Symfony\Component\Lock\LockFactory;

/**
 * Serializes allocation across all returns of an order without holding a database transaction during gateway calls.
 * Wallet payouts contain only local writes, so their sources and completion commit together.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Checkout\OrderReturn\Service\OrderReturnRefundExecutorTest
 */
#[Package('after-sales')]
class OrderReturnRefundExecutor
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LockFactory $lockFactory,
        private readonly PaymentOrderService $payments,
        private readonly PaymentOrderRowLock $paymentLock,
    ) {
    }

    public function claimDestination(string $returnId, string $destination): bool
    {
        $parameters = ['id' => Uuid::fromHexToBytes($returnId), 'destination' => $destination];
        $this->connection->executeStatement(
            'UPDATE order_return SET refund_destination = :destination WHERE id = :id AND refund_destination IS NULL',
            $parameters,
            ['id' => ParameterType::BINARY]
        );

        return $this->connection->fetchOne(
            'SELECT refund_destination FROM order_return WHERE id = :id',
            ['id' => $parameters['id']],
            ['id' => ParameterType::BINARY]
        ) === $destination;
    }

    /**
     * @param \Closure(): void $payout
     */
    public function run(OrderReturnEntity $orderReturn, OrderEntity $order, \Closure $payout): void
    {
        $lock = $this->lockFactory->createLock('order-return-refund-' . $orderReturn->orderId, 300);
        if (!$lock->acquire()) {
            throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'another refund of this order is in progress.');
        }

        try {
            $state = $this->connection->fetchOne(
                'SELECT state.technical_name FROM order_return r INNER JOIN state_machine_state state ON state.id = r.state_id WHERE r.id = :id',
                ['id' => Uuid::fromHexToBytes($orderReturn->id)],
                ['id' => ParameterType::BINARY]
            );
            if (\in_array($state, ['closed', 'refunding'], true)) {
                return;
            }

            if ($orderReturn->refundDestination === OrderReturnEntity::REFUND_WALLET) {
                $transactionId = $order->getPrimaryOrderTransactionId();
                $payment = $transactionId === null ? null : $this->payments->findLatestByOrderTransaction($transactionId);
                $this->connection->transactional(function () use ($payment, $payout, $orderReturn): void {
                    // Acquire the payment lock before the first ledger read establishes a MySQL snapshot.
                    // A concurrent gateway refund must reserve its funds before or after this payout.
                    if ($payment !== null && $this->paymentLock->lockOrder($payment->id) === null) {
                        throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'the collected payment no longer exists.');
                    }
                    $payout();
                });
            } else {
                $payout();
            }
        } finally {
            $lock->release();
        }
    }
}
