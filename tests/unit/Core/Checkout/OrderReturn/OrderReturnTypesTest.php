<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes;
use Shopwell\Core\Framework\Log\Package;

/**
 * The type decides which path a return takes: with the parcel or without it. The two helpers are the
 * only place that answers this, so a plugin type is unknown to them by design and takes no path.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnTypes::class)]
class OrderReturnTypesTest extends TestCase
{
    public function testTheCoreKnowsTwoResolutions(): void
    {
        static::assertSame(['refund_only', 'return_refund'], OrderReturnTypes::all());
    }

    public function testOnlyTheReturnNeedsTheParcel(): void
    {
        static::assertFalse(OrderReturnTypes::requiresReturn(OrderReturnTypes::REFUND_ONLY));
        static::assertTrue(OrderReturnTypes::requiresReturn(OrderReturnTypes::RETURN_REFUND));
    }

    public function testOnlyARefundWithoutGoodsIsPayableOnApproval(): void
    {
        static::assertTrue(OrderReturnTypes::refundableOnApproval(OrderReturnTypes::REFUND_ONLY));
        static::assertFalse(OrderReturnTypes::refundableOnApproval(OrderReturnTypes::RETURN_REFUND));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownTypeProvider(): iterable
    {
        yield 'a plugin type' => ['repair'];
        yield 'an empty type' => [''];
        yield 'a type of the order state machine' => ['cancelled'];
    }

    #[DataProvider('unknownTypeProvider')]
    public function testAnUnknownTypeNeedsNothing(string $type): void
    {
        static::assertFalse(OrderReturnTypes::requiresReturn($type));
        static::assertFalse(OrderReturnTypes::refundableOnApproval($type));
    }
}
