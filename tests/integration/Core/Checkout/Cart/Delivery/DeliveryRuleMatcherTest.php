<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryRuleMatcher;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryValidator;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopwell\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopwell\Core\Checkout\Cart\Rule\DeliveryContainerRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\Rule\ShippingCountryRule;
use Shopwell\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class DeliveryRuleMatcherTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->ids = new IdsCollection();
        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->context->setRuleIds([]);
    }

    public function testShippingCostsAndAvailabilityAreEvaluatedPerDelivery(): void
    {
        $this->createCountries();
        $this->createRules();
        $this->createShippingMethods();

        $germanDelivery = $this->createDelivery($this->getShippingMethod('standard'), $this->getCountry('germany'));
        $frenchDelivery = $this->createDelivery($this->getShippingMethod('express'), $this->getCountry('france'));
        $deliveries = new DeliveryCollection([$germanDelivery, $frenchDelivery]);

        $cart = new Cart(Uuid::randomHex());
        $cart->setPrice(new CartPrice(168.07, 200.0, 200.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));
        $cart->setDeliveries($deliveries);

        $data = new CartDataCollection();
        static::getContainer()->get(DeliveryProcessor::class)->collect($data, $cart, $this->context, new CartBehavior());

        static::getContainer()->get(DeliveryRuleMatcher::class)->match($data, $cart, $deliveries, $this->context);
        static::getContainer()->get(DeliveryCalculator::class)->calculate($data, $cart, $deliveries, $this->context);

        $errors = new ErrorCollection();
        static::getContainer()->get(DeliveryValidator::class)->validate($cart, $errors, $this->context);

        static::assertSame([$this->ids->get('ships-to-germany')], $germanDelivery->getRuleIds());
        static::assertSame([], $frenchDelivery->getRuleIds());

        static::assertSame(2.5, $germanDelivery->getShippingCosts()->getTotalPrice(), 'The rule based price row applies to the delivery to Germany.');
        static::assertSame(20.0, $frenchDelivery->getShippingCosts()->getTotalPrice(), 'The delivery to France falls back to the default price row.');
        static::assertCount(0, $cart->getErrors());

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame($this->ids->get('express'), $error->getParameters()['id'], 'Only the express delivery to France is blocked by the availability rule.');
    }

    public function testDeliveryContainerMatchesTheWeightOfEachDelivery(): void
    {
        $this->createCountries();
        static::getContainer()->get('rule.repository')->create([
            [
                'id' => $this->ids->get('heavy-delivery'),
                'name' => 'Delivery heavier than 30 kg',
                'priority' => 10,
                'conditions' => [
                    [
                        'type' => DeliveryContainerRule::RULE_NAME,
                        'children' => [
                            [
                                'type' => CartWeightRule::RULE_NAME,
                                'value' => ['operator' => CartWeightRule::OPERATOR_GT, 'weight' => 30.0],
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());
        static::getContainer()->get('shipping_method.repository')->create([
            [
                'id' => $this->ids->get('parcel'),
                'active' => true,
                'name' => 'parcel',
                'technicalName' => 'per_delivery_rules_parcel',
                'deliveryTime' => [
                    'id' => $this->ids->get('delivery-time-parcel'),
                    'name' => '1-3 days',
                    'min' => 1,
                    'max' => 3,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
                'prices' => [
                    $this->priceData(5.0, ruleId: null),
                    $this->priceData(25.0, ruleId: $this->ids->get('heavy-delivery')),
                ],
            ],
        ], Context::createDefaultContext());

        $parcel = $this->getShippingMethod('parcel');
        $lightDelivery = $this->createDelivery($parcel, $this->getCountry('germany'), weight: 10.0);
        $heavyDelivery = $this->createDelivery($parcel, $this->getCountry('germany'), weight: 40.0);
        $deliveries = new DeliveryCollection([$lightDelivery, $heavyDelivery]);

        $cart = new Cart(Uuid::randomHex());
        $cart->setPrice(new CartPrice(168.07, 200.0, 200.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));
        $cart->setLineItems(new LineItemCollection([
            ...$lightDelivery->getPositions()->getLineItems(),
            ...$heavyDelivery->getPositions()->getLineItems(),
        ]));
        $cart->setDeliveries($deliveries);

        $data = new CartDataCollection();
        static::getContainer()->get(DeliveryProcessor::class)->collect($data, $cart, $this->context, new CartBehavior());

        static::getContainer()->get(DeliveryRuleMatcher::class)->match($data, $cart, $deliveries, $this->context);
        static::getContainer()->get(DeliveryCalculator::class)->calculate($data, $cart, $deliveries, $this->context);

        static::assertSame([], $lightDelivery->getRuleIds());
        static::assertSame([$this->ids->get('heavy-delivery')], $heavyDelivery->getRuleIds());

        static::assertSame(5.0, $lightDelivery->getShippingCosts()->getTotalPrice(), 'The whole cart weighs 50 kg, the light delivery only 10 kg.');
        static::assertSame(25.0, $heavyDelivery->getShippingCosts()->getTotalPrice());
    }

    private function createCountries(): void
    {
        static::getContainer()->get('country.repository')->create([
            ['id' => $this->ids->get('germany'), 'name' => 'Germany (per delivery rules)'],
            ['id' => $this->ids->get('france'), 'name' => 'France (per delivery rules)'],
        ], Context::createDefaultContext());
    }

    private function createRules(): void
    {
        static::getContainer()->get('rule.repository')->create([
            [
                'id' => $this->ids->get('ships-to-germany'),
                'name' => 'Ships to Germany from 100',
                'priority' => 10,
                'conditions' => [
                    [
                        'type' => ShippingCountryRule::RULE_NAME,
                        'value' => ['operator' => ShippingCountryRule::OPERATOR_EQ, 'countryIds' => [$this->ids->get('germany')]],
                    ],
                    [
                        'type' => CartAmountRule::RULE_NAME,
                        'value' => ['operator' => CartAmountRule::OPERATOR_GTE, 'amount' => 100.0],
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createShippingMethods(): void
    {
        static::getContainer()->get('shipping_method.repository')->create([
            $this->shippingMethodData('standard', defaultPrice: 10.0, germanyPrice: 2.5),
            $this->shippingMethodData('express', defaultPrice: 20.0, germanyPrice: null),
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingMethodData(string $key, float $defaultPrice, ?float $germanyPrice): array
    {
        $prices = [$this->priceData($defaultPrice, ruleId: null)];
        if ($germanyPrice !== null) {
            $prices[] = $this->priceData($germanyPrice, ruleId: $this->ids->get('ships-to-germany'));
        }

        return [
            'id' => $this->ids->get($key),
            'active' => true,
            'name' => $key,
            'technicalName' => 'per_delivery_rules_' . $key,
            'availabilityRuleId' => $this->ids->get('ships-to-germany'),
            'deliveryTime' => [
                'id' => $this->ids->get('delivery-time-' . $key),
                'name' => '1-3 days',
                'min' => 1,
                'max' => 3,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'prices' => $prices,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceData(float $price, ?string $ruleId): array
    {
        return [
            'ruleId' => $ruleId,
            'calculation' => DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT,
            'quantityStart' => 1,
            'currencyPrice' => [
                ['currencyId' => Defaults::CURRENCY, 'net' => $price, 'gross' => $price, 'linked' => false],
            ],
        ];
    }

    private function getShippingMethod(string $key): ShippingMethodEntity
    {
        $shippingMethod = static::getContainer()->get('shipping_method.repository')
            ->search(new Criteria([$this->ids->get($key)]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);

        return $shippingMethod;
    }

    private function getCountry(string $key): CountryEntity
    {
        /** @var CountryCollection $countries */
        $countries = static::getContainer()->get('country.repository')
            ->search(new Criteria([$this->ids->get($key)]), Context::createDefaultContext())
            ->getEntities();
        $country = $countries->first();
        static::assertInstanceOf(CountryEntity::class, $country);

        return $country;
    }

    private function createDelivery(ShippingMethodEntity $shippingMethod, CountryEntity $country, float $weight = 1.0): Delivery
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $lineItem->setShippingCostAware(true);
        $lineItem->setDeliveryInformation(new DeliveryInformation(10, $weight, false));
        $price = new CalculatedPrice(
            100.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(15.97, 19.0, 100.0)]),
            new TaxRuleCollection([new TaxRule(19.0)])
        );
        $lineItem->setPrice($price);

        return new Delivery(
            new DeliveryPositionCollection([
                new DeliveryPosition($lineItem->getId(), $lineItem, 1, $price, new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable())),
            ]),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            ShippingLocation::createFromCountry($country),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
