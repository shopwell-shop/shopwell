<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Checkout\CartRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemOfManufacturerRule::class)]
class LineItemOfManufacturerRuleUnitTest extends TestCase
{
    public function testCustomProductOptionsDoNotMatchNotEqualManufacturerRule(): void
    {
        $manufacturerId = '019fa77183677a04ba9eaff57eed9627';

        $productLineItem = CartRuleFixture::createLineItem()
            ->setPayloadValue('manufacturerId', $manufacturerId);
        $optionLineItem = CartRuleFixture::createLineItem('customized-products-option');

        $customizedProductLineItem = CartRuleFixture::createLineItem('customized-products')
            ->setGood(false)
            ->setChildren(new LineItemCollection([$productLineItem, $optionLineItem]));

        $rule = new LineItemOfManufacturerRule(
            Rule::OPERATOR_NEQ,
            [$manufacturerId],
        );

        $matches = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$customizedProductLineItem])),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertFalse($matches);
    }

    public function testCustomProductOptionDoesNotMatchNotEqualManufacturerRuleWithLineItemScope(): void
    {
        $manufacturerId = '019fa77183677a04ba9eaff57eed9627';

        $rule = new LineItemOfManufacturerRule(
            Rule::OPERATOR_NEQ,
            [$manufacturerId],
        );

        $hasMatch = $rule->match(new LineItemScope(
            CartRuleFixture::createLineItem('customized-products-option'),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertFalse($hasMatch);
    }
}
