<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DeliveryRuleScope::class)]
class DeliveryRuleScopeTest extends TestCase
{
    public function testScopeExposesDeliveryCartAndContext(): void
    {
        $cart = new Cart('test');
        $context = Generator::generateSalesChannelContext();
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $scope = new DeliveryRuleScope($delivery, $cart, $context);

        static::assertSame($delivery, $scope->getDelivery());
        static::assertSame($cart, $scope->getCart());
        static::assertSame($context, $scope->getSalesChannelContext());
        static::assertSame($context->getContext(), $scope->getContext());
    }
}
