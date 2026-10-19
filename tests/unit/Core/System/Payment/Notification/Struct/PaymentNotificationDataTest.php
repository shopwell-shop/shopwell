<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Notification\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotificationData::class)]
class PaymentNotificationDataTest extends TestCase
{
    public function testEveryReferenceDefaultsToNull(): void
    {
        $data = new PaymentNotificationData();

        static::assertNull($data->paymentNo);
        static::assertNull($data->outTradeNo);
        static::assertNull($data->outPaymentNo);
        static::assertNull($data->refundNo);
    }

    public function testTheReferencesAreKeptAsGiven(): void
    {
        $data = new PaymentNotificationData('PG-1', 'T-1', 'CN-1', 'PR-1');

        static::assertSame('PG-1', $data->paymentNo);
        static::assertSame('T-1', $data->outTradeNo);
        static::assertSame('CN-1', $data->outPaymentNo);
        static::assertSame('PR-1', $data->refundNo);
    }

    public function testARefundNotificationOnlyNeedsTheRefundNumber(): void
    {
        $data = new PaymentNotificationData(refundNo: 'PR-1');

        static::assertNull($data->paymentNo);
        static::assertNull($data->outTradeNo);
        static::assertNull($data->outPaymentNo);
        static::assertSame('PR-1', $data->refundNo);
    }
}
