<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopwell\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionProcessor::class)]
class PromotionProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $promotionCalculatorMock = $this->createMock(PromotionCalculator::class);
        $groupBuilderMock = static::createStub(LineItemGroupBuilder::class);

        $promotionProcessor = new PromotionProcessor($promotionCalculatorMock, $groupBuilderMock);

        $originalCart = new Cart('test');
        $originalCart->add(new LineItem('A', 'promotion', 'A', 2)); // 2 items of promotion A

        $toCalculateCart = new Cart('test');
        $toCalculateCart->setPrice(new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET));

        $context = static::createStub(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection(
            [new LineItem('B', PromotionProcessor::LINE_ITEM_TYPE, Uuid::randomHex(), 1)],
        ));

        $promotionCalculatorMock->expects($this->once())
            ->method('calculate')
            ->with(
                static::callback(static function (LineItemCollection $data) {
                    static::assertTrue($data->has('B'));
                    static::assertTrue($data->get('B')->isShippingCostAware());

                    return true;
                }),
                static::anything(),
                static::anything(),
                static::anything()
            );

        $promotionProcessor->process($data, $originalCart, $toCalculateCart, $context, $behavior);
    }

    public function testPinnedRestoredSetPromotionKeepsHistoricalPrice(): void
    {
        $promotionCalculator = $this->createMock(PromotionCalculator::class);
        $promotionProcessor = new PromotionProcessor($promotionCalculator, static::createStub(LineItemGroupBuilder::class));

        $promotion = $this->createRestoredSetPromotion();

        $original = new Cart('original');
        $original->add($promotion);

        $calculated = new Cart('calculated');
        $calculated->setPrice(new CartPrice(100, 100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET));

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection([$promotion]));

        $promotionCalculator->expects($this->once())
            ->method('calculate')
            ->willReturnCallback(static function (LineItemCollection $items, Cart $original, Cart $calculated): void {
                static::assertTrue($items->has('promotion'));
                static::assertSame($original->get('promotion'), $calculated->get('promotion'));
            });

        $promotionProcessor->process(
            $data,
            $original,
            $calculated,
            static::createStub(SalesChannelContext::class),
            new CartBehavior([CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS => true]),
        );

        static::assertSame(-10.0, $calculated->get('promotion')?->getPrice()?->getTotalPrice());
    }

    public function testUnpinnedRestoredSetPromotionIsRecalculated(): void
    {
        $promotionCalculator = $this->createMock(PromotionCalculator::class);
        $promotionProcessor = new PromotionProcessor($promotionCalculator, static::createStub(LineItemGroupBuilder::class));
        $promotion = $this->createRestoredSetPromotion();

        $original = new Cart('original');
        $original->add($promotion);

        $calculated = new Cart('calculated');
        $calculated->setPrice(new CartPrice(100, 100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET));

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection([$promotion]));

        $promotionCalculator->expects($this->once())
            ->method('calculate')
            ->willReturnCallback(static function (LineItemCollection $items, Cart $original, Cart $calculated): void {
                static::assertTrue($items->has('promotion'));
                static::assertNull($calculated->get('promotion'));
            });

        $promotionProcessor->process(
            $data,
            $original,
            $calculated,
            static::createStub(SalesChannelContext::class),
            new CartBehavior(),
        );
    }

    public function testProcessWithCartZeroPriceAndPromotionIsGlobal(): void
    {
        $promotionCalculatorMock = $this->createMock(PromotionCalculator::class);
        $groupBuilderMock = static::createStub(LineItemGroupBuilder::class);

        $promotionProcessor = new PromotionProcessor($promotionCalculatorMock, $groupBuilderMock);

        $originalCart = new Cart('test');
        $originalCart->add(new LineItem('A', 'promotion', 'A', 2)); // 2 items of promotion A

        $toCalculateCart = new Cart('test');
        $toCalculateCart->setPrice(new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET));

        $context = static::createStub(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection(
            // `promotionCodeType` => global means the promotion is automatically applied if matched conditions
            [(new LineItem('B', PromotionProcessor::LINE_ITEM_TYPE, Uuid::randomHex(), 1))->setPayload(['promotionCodeType' => PromotionItemBuilder::PROMOTION_TYPE_GLOBAL])],
        ));

        $promotionCalculatorMock->expects($this->never())
            ->method('calculate');

        $promotionProcessor->process($data, $originalCart, $toCalculateCart, $context, $behavior);

        static::assertCount(0, $toCalculateCart->getErrors());
    }

    public function testProcessWithCartZeroPriceAndPromotionIsNotGlobal(): void
    {
        $promotionCalculatorMock = $this->createMock(PromotionCalculator::class);
        $groupBuilderMock = static::createStub(LineItemGroupBuilder::class);

        $promotionProcessor = new PromotionProcessor($promotionCalculatorMock, $groupBuilderMock);

        $originalCart = new Cart('test');
        $originalCart->add(new LineItem('A', 'promotion', 'A', 2)); // 2 items of promotion A

        $toCalculateCart = new Cart('test');
        $toCalculateCart->setPrice(new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET));

        $context = static::createStub(SalesChannelContext::class);
        $behavior = new CartBehavior();

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection(
            // `promotionCodeType` => fixed means the promotion is applied only if the promotion code is input.
            [(new LineItem('B', PromotionProcessor::LINE_ITEM_TYPE, Uuid::randomHex(), 1))->setPayload(['promotionCodeType' => PromotionItemBuilder::PROMOTION_TYPE_FIXED])],
        ));

        $promotionCalculatorMock->expects($this->never())
            ->method('calculate');

        $promotionProcessor->process($data, $originalCart, $toCalculateCart, $context, $behavior);

        static::assertCount(1, $toCalculateCart->getErrors());
        static::assertInstanceOf(PromotionsOnCartPriceZeroError::class, $toCalculateCart->getErrors()->first());
    }

    private function createRestoredSetPromotion(): LineItem
    {
        return (new LineItem('promotion', PromotionProcessor::LINE_ITEM_TYPE))
            ->setPayload([
                'discountScope' => PromotionDiscountEntity::SCOPE_SET,
                'setGroups' => [['rules' => [['id' => Uuid::randomHex()]]]],
            ])
            ->setPrice(new CalculatedPrice(-10, -10, new CalculatedTaxCollection(), new TaxRuleCollection()));
    }
}
