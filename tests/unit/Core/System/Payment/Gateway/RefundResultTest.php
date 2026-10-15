<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RefundResult::class)]
class RefundResultTest extends TestCase
{
    public function testKeepsTheStatusAndDefaultsToNoChannelReference(): void
    {
        $result = new RefundResult(PaymentRefundStates::STATE_FINISHED);

        static::assertSame(PaymentRefundStates::STATE_FINISHED, $result->status);
        static::assertNull($result->outRefundId);
    }

    public function testKeepsTheChannelReference(): void
    {
        $result = new RefundResult(PaymentRefundStates::STATE_PROCESSING, 'refund-id-42');

        static::assertSame(PaymentRefundStates::STATE_PROCESSING, $result->status);
        static::assertSame('refund-id-42', $result->outRefundId);
    }
}
