<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Wechat\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Wechat\Request\WechatPaymentStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatPaymentStatus::class)]
class WechatPaymentStatusTest extends TestCase
{
    public function testMapsTheTradeStatesWithoutChangingNotificationSemantics(): void
    {
        static::assertSame(QueryResult::STATUS_PAID, WechatPaymentStatus::query('SUCCESS'));
        static::assertSame(QueryResult::STATUS_PAID, WechatPaymentStatus::query('REFUND'));
        static::assertSame(QueryResult::STATUS_CLOSED, WechatPaymentStatus::query('CLOSED'));
        static::assertSame(QueryResult::STATUS_FAILED, WechatPaymentStatus::query('PAYERROR'));
        static::assertSame(QueryResult::STATUS_PENDING, WechatPaymentStatus::query('NOTPAY'));
        static::assertTrue(WechatPaymentStatus::isPaid('REFUND'));
        static::assertTrue(WechatPaymentStatus::isSuccessfulNotification('SUCCESS'));
        static::assertFalse(WechatPaymentStatus::isSuccessfulNotification('REFUND'));
    }
}
