<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotificationResult::class)]
class NotificationResultTest extends TestCase
{
    public function testDefaultsToAnEmptyNotification(): void
    {
        $result = new NotificationResult(true, false);

        static::assertTrue($result->signatureValid);
        static::assertFalse($result->succeeded);
        static::assertNull($result->outPaymentNo);
        static::assertNull($result->outRefundId);
        static::assertNull($result->amount);
        static::assertNull($result->currency);
        static::assertNull($result->status);
        static::assertSame([], $result->raw);
    }

    public function testKeepsEveryFieldOfTheNotification(): void
    {
        $raw = ['trade_status' => 'TRADE_SUCCESS', 'buyer_id' => 'buyer-1'];

        $result = new NotificationResult(
            true,
            true,
            'out-payment-no',
            'out-refund-id',
            1234,
            'CNY',
            'paid',
            $raw,
        );

        static::assertTrue($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('out-payment-no', $result->outPaymentNo);
        static::assertSame('out-refund-id', $result->outRefundId);
        static::assertSame(1234, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame('paid', $result->status);
        static::assertSame($raw, $result->raw);
    }
}
