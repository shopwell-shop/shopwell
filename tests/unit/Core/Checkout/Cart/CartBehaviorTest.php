<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartBehavior::class)]
class CartBehaviorTest extends TestCase
{
    public function testHasPermission(): void
    {
        $cartBehavior = new CartBehavior([CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES => true]);
        static::assertTrue($cartBehavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES));
    }

    public function testHasNoPermission(): void
    {
        $cartBehavior = new CartBehavior([CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES => true]);
        static::assertFalse($cartBehavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_PRICE_OVERWRITES));
    }

    public function testHasNoPermissionWithNoPermissionsSet(): void
    {
        $cartBehavior = new CartBehavior();
        static::assertFalse($cartBehavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_LABEL_OVERWRITES));
    }

    public function testDeprecatedIsRecalculationParameterDoesNotTriggerWhenOmitted(): void
    {
        $this->expectNotToPerformAssertions();

        new CartBehavior([], true);
    }

    public function testDeprecatedIsRecalculationParameterThrowsWhenExplicitlyPassed(): void
    {
        $this->expectException(FeatureException::class);

        new CartBehavior([], true, false);
    }
}
