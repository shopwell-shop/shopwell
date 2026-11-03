<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Paypal\Request;

use PaypalServerSdkLib\Models\CaptureStatus;
use PaypalServerSdkLib\Models\OrderStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Request\PaypalPaymentStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalPaymentStatus::class)]
class PaypalPaymentStatusTest extends TestCase
{
    public function testCaptureAndOrderVocabulariesStaySeparate(): void
    {
        static::assertSame(QueryResult::STATUS_PAID, PaypalPaymentStatus::capture(CaptureStatus::COMPLETED));
        static::assertSame(QueryResult::STATUS_PAID, PaypalPaymentStatus::capture(CaptureStatus::PARTIALLY_REFUNDED));
        static::assertSame(QueryResult::STATUS_PAID, PaypalPaymentStatus::capture(CaptureStatus::REFUNDED));
        static::assertSame(QueryResult::STATUS_FAILED, PaypalPaymentStatus::capture(CaptureStatus::DECLINED));
        static::assertSame(QueryResult::STATUS_FAILED, PaypalPaymentStatus::capture(CaptureStatus::FAILED));
        static::assertSame(QueryResult::STATUS_PENDING, PaypalPaymentStatus::capture('PENDING'));
        static::assertSame(QueryResult::STATUS_CLOSED, PaypalPaymentStatus::order(OrderStatus::VOIDED));
        static::assertSame(QueryResult::STATUS_PENDING, PaypalPaymentStatus::order(OrderStatus::COMPLETED));
    }
}
