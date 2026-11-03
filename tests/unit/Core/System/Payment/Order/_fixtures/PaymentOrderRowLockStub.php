<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures;

use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;

/**
 * Executes payment writes without a database for service unit tests.
 *
 * @internal
 */
final class PaymentOrderRowLockStub extends PaymentOrderRowLock
{
    /**
     * @param array{paidAmount: int, refundedAmount: int} $amounts
     */
    public function __construct(
        private readonly array $amounts = ['paidAmount' => 1000, 'refundedAmount' => 0],
        private readonly int $reserved = 0,
        private readonly bool $settled = false,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $write
     *
     * @return TReturn
     */
    public function transaction(\Closure $write)
    {
        return $write();
    }

    public function lockOrder(string $orderId): array
    {
        return $this->amounts;
    }

    public function lockRefund(string $refundId): bool
    {
        return $this->settled;
    }

    public function reservedRefundAmount(string $orderId): int
    {
        return $this->reserved;
    }
}
