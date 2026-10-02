<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Checkout\CartRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemListPriceRule::class)]
class LineItemListPriceRuleTest extends TestCase
{
    #[DataProviderExternal(CartRuleFixture::class, 'lineItemTypeProvider')]
    public function testLineItemWithListPriceIsEvaluated(string $type, bool $lineItemScope): void
    {
        $rule = new LineItemListPriceRule(Rule::OPERATOR_EQ, 150.0);

        $lineItem = CartRuleFixture::createLineItemWithPrice($type, 100.0, ListPrice::createFromUnitPrice(100.0, 150.0));
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertTrue($rule->match($scope));
    }
}
