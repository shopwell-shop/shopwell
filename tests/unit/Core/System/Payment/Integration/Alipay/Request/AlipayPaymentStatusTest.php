<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Alipay\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Request\AlipayPaymentStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayPaymentStatus::class)]
class AlipayPaymentStatusTest extends TestCase
{
    public function testQueryAndNotificationShareThePaidStates(): void
    {
        static::assertSame(QueryResult::STATUS_PAID, AlipayPaymentStatus::query('TRADE_SUCCESS'));
        static::assertSame(QueryResult::STATUS_PAID, AlipayPaymentStatus::query('TRADE_FINISHED'));
        static::assertTrue(AlipayPaymentStatus::isPaid('TRADE_SUCCESS'));
        static::assertTrue(AlipayPaymentStatus::isPaid('TRADE_FINISHED'));
        static::assertSame(QueryResult::STATUS_CLOSED, AlipayPaymentStatus::query('TRADE_CLOSED'));
        static::assertSame(QueryResult::STATUS_PENDING, AlipayPaymentStatus::query('WAIT_BUYER_PAY'));
        static::assertSame(QueryResult::STATUS_ABSENT, AlipayPaymentStatus::query(null));
        static::assertFalse(AlipayPaymentStatus::isPaid('TRADE_CLOSED'));
    }
}
