<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopwell\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopwell\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopwell\Core\Checkout\Cart\Rule\DeliveryContainerRule;
use Shopwell\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemDimensionWeightRule;
use Shopwell\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Checkout\CartRuleFixture;
use Shopwell\Core\Test\Stub\Rule\FalseRule;
use Shopwell\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DeliveryContainerRule::class)]
class DeliveryContainerRuleTest extends TestCase
{
    private Cart $cart;

    private Delivery $lightDelivery;

    private Delivery $heavyDelivery;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $light = CartRuleFixture::createLineItemWithDeliveryInfo(freeDelivery: false, weight: 10.0, height: 100.0, width: 100.0, length: 100.0);
        $heavy = CartRuleFixture::createLineItemWithDeliveryInfo(freeDelivery: true, weight: 40.0, height: 1000.0, width: 1000.0, length: 1000.0);

        $this->lightDelivery = $this->createDelivery($light);
        $this->heavyDelivery = $this->createDelivery($heavy);

        $this->cart = CartRuleFixture::createCart(new LineItemCollection([$light, $heavy]));
        $this->cart->setDeliveries(new DeliveryCollection([$this->lightDelivery, $this->heavyDelivery]));
        $this->cart->setPrice(new CartPrice(100.0, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->context = static::createStub(SalesChannelContext::class);
    }

    public function testRuleName(): void
    {
        static::assertSame('deliveryContainer', (new DeliveryContainerRule())->getName());
    }

    #[DataProvider('deliveryGoodsRules')]
    public function testMatchesTheLineItemsOfTheDeliveryInADeliveryScope(Rule $rule): void
    {
        $container = new DeliveryContainerRule([$rule]);

        static::assertFalse($container->match(new DeliveryRuleScope($this->lightDelivery, $this->cart, $this->context)));
        static::assertTrue($container->match(new DeliveryRuleScope($this->heavyDelivery, $this->cart, $this->context)));
    }

    #[DataProvider('deliveryGoodsRules')]
    public function testRulesOutsideTheContainerMatchTheWholeCartInADeliveryScope(Rule $rule): void
    {
        static::assertTrue($rule->match(new DeliveryRuleScope($this->lightDelivery, $this->cart, $this->context)));
    }

    #[DataProvider('deliveryGoodsRules')]
    public function testMatchesTheWholeCartOutsideADeliveryScope(Rule $rule): void
    {
        $container = new DeliveryContainerRule([$rule]);

        static::assertTrue($container->match(new CartRuleScope($this->cart, $this->context)));
    }

    public static function deliveryGoodsRules(): \Generator
    {
        yield 'cart weight' => [new CartWeightRule(Rule::OPERATOR_GT, 20.0)];
        yield 'cart volume' => [new CartVolumeRule(Rule::OPERATOR_GT, 0.5)];
        yield 'cart has free delivery item' => [new CartHasDeliveryFreeItemRule(true)];
        yield 'line item weight' => [new LineItemDimensionWeightRule(Rule::OPERATOR_GT, 20.0)];
    }

    public function testKeepsThePriceOfTheCart(): void
    {
        $container = new DeliveryContainerRule([new CartAmountRule(Rule::OPERATOR_GTE, 100.0)]);

        static::assertTrue($container->match(new DeliveryRuleScope($this->lightDelivery, $this->cart, $this->context)));
    }

    public function testKeepsTheDataOfTheCart(): void
    {
        $this->cart->getData()->set('collected', true);
        $readsCartData = new class extends Rule {
            final public const RULE_NAME = 'readsCartData';

            public function match(RuleScope $scope): bool
            {
                return $scope instanceof CartRuleScope && $scope->getCart()->getData()->has('collected');
            }

            public function getConstraints(): array
            {
                return [];
            }
        };
        $container = new DeliveryContainerRule([$readsCartData]);

        static::assertTrue($container->match(new DeliveryRuleScope($this->lightDelivery, $this->cart, $this->context)));
    }

    public function testKeepsTheDeliveryForShippingRules(): void
    {
        $shippingMethodRule = (new ShippingMethodRule())->assign([
            'operator' => Rule::OPERATOR_EQ,
            'shippingMethodIds' => [$this->lightDelivery->getShippingMethod()->getId()],
        ]);
        $container = new DeliveryContainerRule([$shippingMethodRule]);

        static::assertTrue($container->match(new DeliveryRuleScope($this->lightDelivery, $this->cart, $this->context)));
        static::assertFalse($container->match(new DeliveryRuleScope($this->heavyDelivery, $this->cart, $this->context)));
    }

    /**
     * @param list<Rule> $rules
     */
    #[DataProvider('ruleCombinations')]
    public function testMatchesWhenAllRulesMatch(array $rules, bool $expected): void
    {
        $container = new DeliveryContainerRule($rules);

        static::assertSame($expected, $container->match(new DeliveryRuleScope($this->lightDelivery, $this->cart, $this->context)));
    }

    public static function ruleCombinations(): \Generator
    {
        yield 'all match' => [[new TrueRule(), new TrueRule()], true];
        yield 'one does not match' => [[new TrueRule(), new FalseRule()], false];
    }

    private function createDelivery(LineItem $lineItem): Delivery
    {
        $deliveryDate = new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable());
        $price = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        return new Delivery(
            new DeliveryPositionCollection([
                new DeliveryPosition($lineItem->getId(), $lineItem, $lineItem->getQuantity(), $price, $deliveryDate),
            ]),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            $price
        );
    }
}
