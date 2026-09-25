<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Checkout\Cart\Rule\LineItemRule;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartScopeDiscountPackager::class)]
class CartScopeDiscountPackagerTest extends TestCase
{
    /**
     * @param array<string, string|array<mixed>> $payload
     */
    #[DataProvider('dataProvider')]
    public function testGetMatchingItems(LineItem $matchingLineItem, array $payload, LineItemQuantityCollection $quantityCollection): void
    {
        $context = Generator::generateSalesChannelContext();

        $cart = new Cart('foo');
        $cart->setLineItems(
            new LineItemCollection([
                $matchingLineItem,
                (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()))->setStackable(true),
            ])
        );

        $priceDefinition = new AbsolutePriceDefinition(42, new LineItemRule(Rule::OPERATOR_EQ, [$matchingLineItem->getReferencedId() ?? '']));
        $discount = new DiscountLineItem('foo', $priceDefinition, $payload, null);

        $packager = new CartScopeDiscountPackager();
        $items = $packager->getMatchingItems($discount, $cart, $context);

        $expected = new DiscountPackageCollection([new DiscountPackage($quantityCollection)]);

        static::assertEquals($expected, $items);

        $priceDefinition = new PercentagePriceDefinition(42, new LineItemRule(Rule::OPERATOR_EQ, [Uuid::randomHex()]));
        $discount = new DiscountLineItem('foo', $priceDefinition, $payload, null);

        $items = $packager->getMatchingItems($discount, $cart, $context);

        static::assertEquals(new DiscountPackageCollection([]), $items);
    }

    /**
     * @return iterable<array{0: LineItem, 1: array<string, string|array<mixed>>, 2: LineItemQuantityCollection}>
     */
    public static function dataProvider(): iterable
    {
        $item = (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 2))->setStackable(true);

        yield 'not consider rules' => [
            $item,
            [
                'discountScope' => 'foo',
                'discountType' => 'bar',
                'filter' => [
                    'considerAdvancedRules' => false,
                ],
            ],
            new LineItemQuantityCollection([
                new LineItemQuantity($item->getId(), 2),
            ]),
        ];

        yield 'consider rules' => [
            $item,
            [
                'discountScope' => 'foo',
                'discountType' => 'bar',
                'filter' => [
                    'considerAdvancedRules' => true,
                ],
            ],
            new LineItemQuantityCollection([
                new LineItemQuantity($item->getId(), 1),
                new LineItemQuantity($item->getId(), 1),
            ]),
        ];

        yield 'not consider rules, no filter value' => [
            $item,
            [
                'discountScope' => 'foo',
                'discountType' => 'bar',
            ],
            new LineItemQuantityCollection([
                new LineItemQuantity($item->getId(), 2),
            ]),
        ];
    }
}
