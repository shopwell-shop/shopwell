<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Stripe\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Request\StripePaymentStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripePaymentStatus::class)]
class StripePaymentStatusTest extends TestCase
{
    public function testIntentAndSessionUseTheirOwnTerminalConditions(): void
    {
        static::assertSame(QueryResult::STATUS_PAID, StripePaymentStatus::intent('succeeded'));
        static::assertSame(QueryResult::STATUS_CLOSED, StripePaymentStatus::intent('canceled'));
        static::assertSame(QueryResult::STATUS_PENDING, StripePaymentStatus::intent('processing'));
        static::assertSame(QueryResult::STATUS_PAID, StripePaymentStatus::session('complete', 'paid'));
        static::assertSame(QueryResult::STATUS_PENDING, StripePaymentStatus::session('complete', 'unpaid'));
        static::assertSame(QueryResult::STATUS_CLOSED, StripePaymentStatus::session('expired', 'paid'));
    }
}
