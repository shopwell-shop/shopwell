<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\QueryResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(QueryResult::class)]
class QueryResultTest extends TestCase
{
    public function testDefaultsToABareAnswer(): void
    {
        $result = new QueryResult(QueryResult::STATUS_PENDING);

        static::assertSame(QueryResult::STATUS_PENDING, $result->status);
        static::assertNull($result->outPaymentNo);
        static::assertNull($result->amount);
        static::assertNull($result->currency);
        static::assertNull($result->channelStatus);
        static::assertSame([], $result->raw);
    }

    public function testKeepsEveryFieldOfTheAnswer(): void
    {
        $raw = ['trade_state' => 'SUCCESS', 'transaction_id' => 'transaction-1'];

        $result = new QueryResult(
            QueryResult::STATUS_PAID,
            'transaction-1',
            1234,
            'CNY',
            'SUCCESS',
            $raw,
        );

        static::assertSame(QueryResult::STATUS_PAID, $result->status);
        static::assertSame('transaction-1', $result->outPaymentNo);
        static::assertSame(1234, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame('SUCCESS', $result->channelStatus);
        static::assertSame($raw, $result->raw);
    }
}
