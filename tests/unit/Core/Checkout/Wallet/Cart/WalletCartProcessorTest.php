<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Cart;

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
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletCartProcessor::class)]
class WalletCartProcessorTest extends TestCase
{
    private WalletService&Stub $walletService;

    private AbsolutePriceCalculator&Stub $calculator;

    private CalculatedPrice $calculatedPrice;

    protected function setUp(): void
    {
        $this->walletService = static::createStub(WalletService::class);
        $this->calculator = static::createStub(AbsolutePriceCalculator::class);
        $this->calculatedPrice = new CalculatedPrice(-1.0, -1.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $this->calculator->method('calculate')->willReturn($this->calculatedPrice);
    }

    public function testRemovesLineItemWhenNoAmountIsRequested(): void
    {
        $cart = $this->createCart();

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID));
        static::assertFalse($cart->hasExtension(WalletCartProcessor::EXTENSION_KEY));
    }

    public function testRemovesLineItemWhenCustomerIsMissing(): void
    {
        $cart = $this->createCart(50.0);

        $context = static::createStub(SalesChannelContext::class);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $context, new CartBehavior());

        static::assertNull($cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID));
    }

    public function testRemovesLineItemWhenTheWalletIsDisabled(): void
    {
        $cart = $this->createCart(50.0);
        $this->walletService->method('getBalance')->willReturn(1000.0);

        $this->createProcessor([WalletSettings::CONFIG_KEY_ENABLED => false])
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID));
    }

    public function testClampsPaymentToCustomerBalance(): void
    {
        $cart = $this->createCart(1000.0);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(500.0)]));
        $this->walletService->method('getBalance')->willReturn(100.0);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(100.0, $lineItem->getPayloadValue('amount'));

        $extension = $cart->getExtension(WalletCartProcessor::EXTENSION_KEY);
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(100.0, $extension->get('amount'));
    }

    public function testClampsPaymentToMaxPercentageOfOrderTotal(): void
    {
        $cart = $this->createCart(1000.0);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(500.0)]));
        $this->walletService->method('getBalance')->willReturn(1000.0);

        $this->createProcessor([WalletSettings::CONFIG_KEY_MAX_PAYMENT_PERCENT => 40.0])
            ->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(200.0, $lineItem->getPayloadValue('amount'));
    }

    public function testClampsPaymentAfterOtherCreditLineItems(): void
    {
        $cart = $this->createCart(1000.0);
        $cart->setLineItems(new LineItemCollection([
            $this->createGoodsLineItem(500.0),
            $this->createCreditLineItem('loyalty-points', 200.0),
        ]));
        $this->walletService->method('getBalance')->willReturn(1000.0);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(300.0, $lineItem->getPayloadValue('amount'));
    }

    public function testRemovesLineItemWhenNothingIsLeftToPay(): void
    {
        $cart = $this->createCart(50.0);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(100.0), $this->createCreditLineItem('other', 100.0)]));
        $this->walletService->method('getBalance')->willReturn(1000.0);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        static::assertNull($cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID));
    }

    public function testMaterializesCreditLineItem(): void
    {
        $cart = $this->createCart(100.0);
        $cart->setLineItems(new LineItemCollection([$this->createGoodsLineItem(500.0)]));
        $this->walletService->method('getBalance')->willReturn(100.0);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(LineItem::CREDIT_LINE_ITEM_TYPE, $lineItem->getType());
        static::assertFalse($lineItem->isGood());
        static::assertFalse($lineItem->isRemovable());
        static::assertFalse($lineItem->isStackable());
        static::assertFalse($lineItem->isShippingCostAware());
        static::assertSame('wallet-payment', $lineItem->getLabel());

        $priceDefinition = $lineItem->getPriceDefinition();
        static::assertInstanceOf(AbsolutePriceDefinition::class, $priceDefinition);
        static::assertSame(-100.0, $priceDefinition->getPrice());
        static::assertSame($this->calculatedPrice, $lineItem->getPrice());
    }

    public function testReusesExistingLineItemWithoutIncreasingQuantity(): void
    {
        $cart = $this->createCart(100.0);
        $cart->setLineItems(new LineItemCollection([
            $this->createGoodsLineItem(500.0),
            new LineItem(WalletCartProcessor::LINE_ITEM_ID, LineItem::CREDIT_LINE_ITEM_TYPE),
        ]));
        $this->walletService->method('getBalance')->willReturn(100.0);

        $this->createProcessor()->process(new CartDataCollection(), new Cart('original'), $cart, $this->createContext(), new CartBehavior());

        $lineItem = $cart->getLineItems()->get(WalletCartProcessor::LINE_ITEM_ID);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(1, $lineItem->getQuantity());
        static::assertSame(100.0, $lineItem->getPayloadValue('amount'));
    }

    private function createContext(): SalesChannelContext
    {
        return Generator::generateSalesChannelContext();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProcessor(array $config = []): WalletCartProcessor
    {
        $config = array_merge([
            WalletSettings::CONFIG_KEY_MAX_PAYMENT_PERCENT => 100.0,
        ], $config);

        return new WalletCartProcessor(
            $this->calculator,
            new WalletSettings(new StaticSystemConfigService($config)),
            $this->walletService
        );
    }

    private function createCart(float $requestedAmount = 0.0): Cart
    {
        $cart = new Cart('test');

        if ($requestedAmount > 0.0) {
            $cart->addExtension(WalletCartProcessor::EXTENSION_KEY, new ArrayStruct(['amount' => $requestedAmount]));
        }

        $cart->add(new LineItem(WalletCartProcessor::LINE_ITEM_ID, LineItem::CREDIT_LINE_ITEM_TYPE));

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
