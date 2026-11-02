<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionCalculator;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionRates;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointCartProcessor::class)]
class LoyaltyPointCartProcessorTest extends TestCase
{
    private LoyaltyAccountService&Stub $accounts;

    private AbsolutePriceCalculator&Stub $calculator;

    private CalculatedPrice $calculatedPrice;

    protected function setUp(): void
    {
        $this->accounts = static::createStub(LoyaltyAccountService::class);
        $this->calculator = static::createStub(AbsolutePriceCalculator::class);
        $this->calculatedPrice = new CalculatedPrice(-1.0, -1.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $this->calculator->method('calculate')->willReturn($this->calculatedPrice);
    }

    public function testRemovesLineItemWhenNoPointsAreRequested(): void
    {
        $cart = $this->createCart();

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID));
        static::assertFalse($cart->hasExtension(LoyaltyPointCartProcessor::EXTENSION_KEY));
    }

    public function testRemovesLineItemWhenCustomerIsMissing(): void
    {
        $cart = $this->createCart(1000);

        $context = static::createStub(SalesChannelContext::class);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $context, new CartBehavior());

        static::assertNull($cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID));
    }

    public function testRemovesLineItemWhenTheMembershipIsDisabled(): void
    {
        $cart = $this->createCart(100);

        $this->createProcessor([LoyaltySettings::CONFIG_KEY_ENABLED => false])
            ->process(new CartDataCollection(), new Cart('original'), $cart, Generator::generateSalesChannelContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID));
    }

    public function testRemovesLineItemWhenPointValueIsNotConfigured(): void
    {
        $cart = $this->createCart(1000);

        $this->createProcessor([LoyaltySettings::CONFIG_KEY_POINT_VALUE_IN_CURRENCY => 0.0])
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID));
    }

    public function testRemovesLineItemWhenRequestedPointsAreBelowMinimum(): void
    {
        $cart = $this->createCart(50);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(1000.0)]));
        $this->accounts->method('balance')->willReturn(1000);

        $this->createProcessor([LoyaltySettings::CONFIG_KEY_MIN_REDEEMABLE_POINTS => 100])
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID));
    }

    public function testClampsRedemptionToCustomerBalance(): void
    {
        $cart = $this->createCart(1000);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(500.0)]));
        $this->accounts->method('balance')->willReturn(100);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(100, $lineItem->getPayloadValue('points'));

        $extension = $cart->getExtension(LoyaltyPointCartProcessor::EXTENSION_KEY);
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(100, $extension->get('points'));
    }

    public function testClampsRedemptionToMaxPercentageOfOrderTotal(): void
    {
        $cart = $this->createCart(1000);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(500.0)]));
        $this->accounts->method('balance')->willReturn(1000);

        $this->createProcessor([LoyaltySettings::CONFIG_KEY_MAX_REDEEMABLE_PERCENT => 50.0])
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(250, $lineItem->getPayloadValue('points'));
    }

    public function testClampsRedemptionAfterOtherCreditLineItems(): void
    {
        $cart = $this->createCart(1000);
        $cart->setLineItems(new LineItemCollection([
            $this->createGoodsLineItem(500.0),
            $this->createCreditLineItem('voucher', 200.0),
        ]));
        $this->accounts->method('balance')->willReturn(1000);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(300, $lineItem->getPayloadValue('points'));
    }

    public function testMaterializesCreditLineItem(): void
    {
        $cart = $this->createCart(100);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(500.0)]));
        $this->accounts->method('balance')->willReturn(100);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(LineItem::CREDIT_LINE_ITEM_TYPE, $lineItem->getType());
        static::assertFalse($lineItem->isGood());
        static::assertFalse($lineItem->isRemovable());
        static::assertFalse($lineItem->isStackable());
        static::assertFalse($lineItem->isShippingCostAware());
        static::assertSame('loyalty-points', $lineItem->getLabel());

        $priceDefinition = $lineItem->getPriceDefinition();
        static::assertInstanceOf(AbsolutePriceDefinition::class, $priceDefinition);
        static::assertSame(-100.0, $priceDefinition->getPrice());
        static::assertSame($this->calculatedPrice, $lineItem->getPrice());
    }

    public function testReusesExistingLineItemWithoutIncreasingQuantity(): void
    {
        $cart = $this->createCart(100);
        $cart->setLineItems(new LineItemCollection([
            $this->createGoodsLineItem(500.0),
            new LineItem(LoyaltyPointCartProcessor::LINE_ITEM_ID, LineItem::CREDIT_LINE_ITEM_TYPE),
        ]));
        $this->accounts->method('balance')->willReturn(100);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(1, $lineItem->getQuantity());
        static::assertSame(100, $lineItem->getPayloadValue('points'));
    }

    public function testUsesTheLadderForTheDiscountAmount(): void
    {
        $cart = $this->createCart(1500);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(5000.0)]));
        $this->accounts->method('balance')->willReturn(5000);

        $this->createProcessor([], $this->rates([1000 => 2.0]))
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);

        $priceDefinition = $lineItem->getPriceDefinition();
        static::assertInstanceOf(AbsolutePriceDefinition::class, $priceDefinition);
        // 1000 points at the configured rate, the 500 above the step at twice that
        static::assertSame(-2000.0, $priceDefinition->getPrice());
    }

    public function testClampsToWhatTheOrderMayGiveAwayAtLadderRates(): void
    {
        $cart = $this->createCart(5000);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(1000.0)]));
        $this->accounts->method('balance')->willReturn(5000);

        $this->createProcessor([], $this->rates([1000 => 2.0]))
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(LoyaltyPointCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(1000, $lineItem->getPayloadValue('points'));
    }

    private function createContext(): SalesChannelContext
    {
        return Generator::generateSalesChannelContext();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProcessor(array $config = [], ?LoyaltyRedemptionRates $rates = null): LoyaltyPointCartProcessor
    {
        $config = array_merge([
            LoyaltySettings::CONFIG_KEY_POINT_VALUE_IN_CURRENCY => 1.0,
            LoyaltySettings::CONFIG_KEY_MIN_REDEEMABLE_POINTS => 1,
            LoyaltySettings::CONFIG_KEY_MAX_REDEEMABLE_PERCENT => 100.0,
        ], $config);

        $settings = new LoyaltySettings(new StaticSystemConfigService($config));

        $redemption = static::createStub(LoyaltyRedemptionCalculator::class);
        $redemption->method('rates')->willReturn(
            $rates ?? LoyaltyRedemptionRates::fromLevels(new LoyaltyRedemptionLevelCollection(), $settings->pointValueInCurrency())
        );

        return new LoyaltyPointCartProcessor(
            $this->calculator,
            $redemption,
            $settings,
            $this->accounts
        );
    }

    /**
     * @param array<int, float> $steps points => value of one point from there on
     */
    private function rates(array $steps): LoyaltyRedemptionRates
    {
        $levels = new LoyaltyRedemptionLevelCollection();

        foreach ($steps as $points => $valuePerPoint) {
            $level = new LoyaltyRedemptionLevelEntity();
            $level->setId(Uuid::randomHex());
            $level->setPoints($points);
            $level->setValuePerPoint($valuePerPoint);
            $levels->add($level);
        }

        return LoyaltyRedemptionRates::fromLevels($levels, 1.0);
    }

    private function createCart(int $requestedPoints = 0): Cart
    {
        $cart = new Cart('test');

        if ($requestedPoints > 0) {
            $cart->addExtension(LoyaltyPointCartProcessor::EXTENSION_KEY, new ArrayStruct(['points' => $requestedPoints]));
        }

        $cart->add(new LineItem(LoyaltyPointCartProcessor::LINE_ITEM_ID, LineItem::CREDIT_LINE_ITEM_TYPE));

        return $cart;
    }

    private function createGoodsLineItem(float $total): LineItem
    {
        $lineItem = new LineItem('goods-1', LineItem::PRODUCT_LINE_ITEM_TYPE, 'goods-1');
        $lineItem->setPrice(new CalculatedPrice($total, $total, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $lineItem;
    }

    private function createCreditLineItem(string $id, float $total): LineItem
    {
        $lineItem = new LineItem($id, LineItem::CREDIT_LINE_ITEM_TYPE, $id);
        $lineItem->setGood(false);
        $lineItem->setPrice(new CalculatedPrice(-$total, -$total, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $lineItem;
    }
}
