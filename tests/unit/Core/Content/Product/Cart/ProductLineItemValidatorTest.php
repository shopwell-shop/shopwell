<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Content\Product\Cart\ProductCartProcessor;
use Shopwell\Core\Content\Product\Cart\ProductLineItemValidator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProductLineItemValidator::class)]
class ProductLineItemValidatorTest extends TestCase
{
    public function testSkipStockValidation(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new ProductLineItemFactory(new PriceDefinitionFactory());
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $cart->add(
            $builder
                ->create(['id' => 'product-1', 'referencedId' => 'product-1'], $salesChannelContext)
                ->setQuantityInformation(
                    (new QuantityInformation())
                        ->setMinPurchase(1)
                        ->setMaxPurchase(1)
                        ->setPurchaseSteps(1)
                )
        );
        $cart->add(
            $builder
                ->create(['id' => 'product-2', 'referencedId' => 'product-2'], $salesChannelContext)
                ->setReferencedId('product-1')
                ->setQuantityInformation(
                    (new QuantityInformation())
                        ->setMinPurchase(1)
                        ->setMaxPurchase(1)
                        ->setPurchaseSteps(1)
                )
        );

        $cart->setBehavior(new CartBehavior([
            ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => true,
        ]));

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $salesChannelContext);

        static::assertCount(0, $cart->getErrors());
    }

    public function testValidateOnDuplicateProductsAtMaxPurchase(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new ProductLineItemFactory(new PriceDefinitionFactory());
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $cart->add(
            $builder
            ->create(['id' => 'product-1', 'referencedId' => 'product-1'], $salesChannelContext)
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(1)
                ->setPurchaseSteps(1)
            )
        );
        $cart->add(
            $builder
            ->create(['id' => 'product-2', 'referencedId' => 'product-2'], $salesChannelContext)
            ->setReferencedId('product-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(1)
                ->setPurchaseSteps(1)
            )
        );

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $salesChannelContext);

        static::assertCount(1, $cart->getErrors());
    }

    public function testValidateOnDuplicateProductsWithSafeQuantity(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new ProductLineItemFactory(new PriceDefinitionFactory());
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $cart->add(
            $builder
            ->create(['id' => 'product-1', 'referencedId' => 'product-1'], $salesChannelContext)
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(3)
                ->setPurchaseSteps(1)
            )
        );
        $cart->add(
            $builder
            ->create(['id' => 'product-2', 'referencedId' => 'product-2'], $salesChannelContext)
            ->setReferencedId('product-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(3)
                ->setPurchaseSteps(1)
            )
        );

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $salesChannelContext);

        static::assertCount(0, $cart->getErrors());
    }

    public function testValidateOnDuplicateProductsWithoutQuantityInformation(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new ProductLineItemFactory(new PriceDefinitionFactory());
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $cart->add($builder->create(['id' => 'product-1', 'referencedId' => 'product-1'], $salesChannelContext));
        $cart->add($builder->create(['id' => 'product-2', 'referencedId' => 'product-2'], $salesChannelContext)->setReferencedId('product-1'));

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $salesChannelContext);

        static::assertCount(0, $cart->getErrors());
    }
}
