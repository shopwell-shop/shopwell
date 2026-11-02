<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront\Settlement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PurchaseAttach::class)]
class PurchaseAttachTest extends TestCase
{
    private const PURCHASE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testMarkerRoundTripsToThePurchaseItWasBuiltFor(): void
    {
        $attach = new PurchaseAttach('membership_order:');

        static::assertSame(
            self::PURCHASE_ID,
            $attach->purchaseIdOf($attach->forPurchase(self::PURCHASE_ID))
        );
    }

    /**
     * @return iterable<string, array{attach: ?string}>
     */
    public static function foreignMarkers(): iterable
    {
        yield 'null' => ['attach' => null];
        yield 'other purchase' => ['attach' => 'wallet_top_up:' . self::PURCHASE_ID];
        yield 'same prefix without an id' => ['attach' => 'membership_order:'];
        yield 'unrelated value' => ['attach' => 'order-4711'];
    }

    #[DataProvider('foreignMarkers')]
    public function testPaymentOrdersOfOtherPurchasesAreNotClaimed(?string $attach): void
    {
        static::assertNull((new PurchaseAttach('membership_order:'))->purchaseIdOf($attach));
    }
}
