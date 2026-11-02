<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront\Settlement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlementResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PurchaseSettlementResult::class)]
class PurchaseSettlementResultTest extends TestCase
{
    /**
     * @return iterable<string, array{result: PurchaseSettlementResult, settled: bool}>
     */
    public static function results(): iterable
    {
        yield 'settled' => ['result' => PurchaseSettlementResult::Settled, 'settled' => true];
        yield 'already settled' => ['result' => PurchaseSettlementResult::AlreadySettled, 'settled' => false];
        yield 'amount mismatch' => ['result' => PurchaseSettlementResult::AmountMismatch, 'settled' => false];
        yield 'not settleable' => ['result' => PurchaseSettlementResult::NotSettleable, 'settled' => false];
        yield 'not found' => ['result' => PurchaseSettlementResult::NotFound, 'settled' => false];
    }

    #[DataProvider('results')]
    public function testOnlyAFreshSettlementCountsAsSettled(PurchaseSettlementResult $result, bool $settled): void
    {
        static::assertSame($settled, $result->isSettled());
    }

    public function testValuesArePartOfTheContract(): void
    {
        static::assertSame('already_settled', PurchaseSettlementResult::AlreadySettled->value);
        static::assertSame('amount_mismatch', PurchaseSettlementResult::AmountMismatch->value);
    }
}
