<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Checkout;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Builds the line items and carts the cart rule tests match against. Every method returns a fresh object,
 * there is no shared state, so it is a plain static helper instead of a trait mixed into each test class.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CartRuleFixture
{
    public static function createLineItem(
        string $type = LineItem::PRODUCT_LINE_ITEM_TYPE,
        int $quantity = 1,
        ?string $referenceId = null
    ): LineItem {
        return new LineItem(Uuid::randomHex(), $type, $referenceId, $quantity);
    }

    public static function createLineItemWithDeliveryInfo(
        bool $freeDelivery,
        int $quantity = 1,
        ?float $weight = 50.0,
        ?float $height = null,
        ?float $width = null,
        ?float $length = null,
        int $stock = 9999
    ): LineItem {
        return self::createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $quantity)->setDeliveryInformation(
            new DeliveryInformation(
                $stock,
                $weight,
                $freeDelivery,
                null,
                (new DeliveryTime())->assign([
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'weeks',
                    'name' => '1-3 weeks',
                ]),
                $height,
                $width,
                $length
            )
        );
    }

    public static function createContainerLineItem(LineItemCollection $childLineItemCollection): LineItem
    {
        return self::createLineItem('container-type')->setChildren($childLineItemCollection);
    }

    public static function createLineItemWithPrice(string $type, float $price, ?ListPrice $listPrice = null): LineItem
    {
        return self::createLineItem($type)->setPrice(
            new CalculatedPrice(
                $price,
                $price,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                1,
                null,
                $listPrice
            )
        );
    }

    public static function createCart(LineItemCollection $lineItemCollection): Cart
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->addLineItems($lineItemCollection);

        return $cart;
    }
}
