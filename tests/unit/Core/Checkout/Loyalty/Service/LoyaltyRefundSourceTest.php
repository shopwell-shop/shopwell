<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRefundSource;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyReturnPoints;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * The points program is one source of a return and answers in the currency of the return, so the
 * refund service can split the amount without knowing that one of the parts is paid in points.
 *
 * The rules behind the two numbers are covered by
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service\LoyaltyReturnPointsTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyRefundSource::class)]
class LoyaltyRefundSourceTest extends TestCase
{
    public function testTheRefundableAmountComesFromThePointsOfTheOrder(): void
    {
        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->once())
            ->method('refundableValue')
            ->with($this->orderReturn(), $this->order(), $this->context())
            ->willReturn(300);

        static::assertSame(300, (new LoyaltyRefundSource($points))->refundableAmount($this->orderReturn(), $this->order(), $this->context()));
    }

    public function testARefundGivesThePointsBack(): void
    {
        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->once())
            ->method('giveBack')
            ->with($this->orderReturn(), $this->order(), 300, $this->context());

        (new LoyaltyRefundSource($points))->refund($this->orderReturn(), $this->order(), 300, $this->context());
    }

    private function context(): Context
    {
        return Context::createDefaultContext();
    }

    private function order(): OrderEntity
    {
        return (new OrderEntity())->assign(['id' => 'order-id']);
    }

    private function orderReturn(): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => 'order-id',
            'customerId' => 'customer-id',
            'currency' => 'CNY',
            'refundAmount' => 600,
        ]);
    }
}
