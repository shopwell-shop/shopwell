<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Extension\ProductShippingCostRouteExtension;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Processor;
use Shopwell\Core\Checkout\Cart\SalesChannel\ProductShippingCostRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\ShippingCostRouteResponse;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionDeliveryProcessor;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProductShippingCostRoute::class)]
class ProductShippingCostRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new ProductShippingCostRoute(
            static::createStub(ProductGatewayInterface::class),
            static::createStub(EntityRepository::class),
            static::createStub(Processor::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testShippingCostsByProductWithShippingMethodCriteriaReturnsRequestedShippingMethods(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);
        $product = $this->createProduct('product-1');
        $shippingMethods = new ShippingMethodCollection([$shippingMethod]);

        $route = new ProductShippingCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock($shippingMethods, $context, [$shippingMethod->getId()]),
            $this->createProcessorMock(1),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $response = $route->shippingCostsByProduct($product->getId(), new Criteria([$shippingMethod->getId()]), $context);

        static::assertCount(1, $response->getShippingCosts());
        static::assertSame(10.0, $response->getShippingCost($shippingMethod->getId())?->getTotalPrice());
        static::assertSame($shippingMethod, $response->getShippingMethod($shippingMethod->getId()));
    }

    public function testShippingCostsByProductAllowsContextPermissionsToEnableSkippedBehaviorExceptCartPersistence(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);

        $product = $this->createProduct('product-1');
        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->once())
            ->method('process')
            ->with(
                static::isInstanceOf(Cart::class),
                static::isInstanceOf(SalesChannelContext::class),
                static::callback(function (CartBehavior $behavior): bool {
                    static::assertFalse($behavior->hasPermission(CheckoutPermissions::SKIP_PROMOTION));
                    static::assertFalse($behavior->hasPermission(PromotionDeliveryProcessor::SKIP_DELIVERY_RECALCULATION));
                    static::assertFalse($behavior->hasPermission(CheckoutPermissions::SKIP_PRODUCT_STOCK_VALIDATION));
                    static::assertTrue($behavior->hasPermission(CheckoutPermissions::SKIP_CART_PERSISTENCE));

                    return true;
                })
            )
            ->willReturn(Generator::createCartWithDelivery());

        $route = new ProductShippingCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock(new ShippingMethodCollection([$shippingMethod]), $context, [$shippingMethod->getId()]),
            $processor,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $context->assign(['permissions' => [
            CheckoutPermissions::SKIP_PROMOTION => false,
            PromotionDeliveryProcessor::SKIP_DELIVERY_RECALCULATION => false,
            CheckoutPermissions::SKIP_PRODUCT_STOCK_VALIDATION => false,
            CheckoutPermissions::SKIP_CART_PERSISTENCE => false,
        ]]);

        $response = $route->shippingCostsByProduct($product->getId(), new Criteria([$shippingMethod->getId()]), $context);

        static::assertCount(1, $response->getShippingCosts());
    }

    public function testShippingCostsByProductSumsShippingCostsOfAllDeliveries(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);
        $product = $this->createProduct('product-1');
        $deliveryDate = new DeliveryDate(new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2024-01-03'));
        $processedCart = $this->createCartWithDeliveries($shippingMethod, $deliveryDate, 8.5, -2.2);

        $route = new ProductShippingCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock(new ShippingMethodCollection([$shippingMethod]), $context, [$shippingMethod->getId()]),
            $this->createProcessorMock(1, $processedCart),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $response = $route->shippingCostsByProduct($product->getId(), new Criteria([$shippingMethod->getId()]), $context);

        static::assertCount(1, $response->getShippingCosts());
        static::assertEqualsWithDelta(6.3, $response->getShippingCost($shippingMethod->getId())?->getTotalPrice(), 0.001);
        static::assertSame($deliveryDate, $response->getDeliveryDate($shippingMethod->getId()));
    }

    public function testShippingCostsByProductWithoutCriteriaIdsReturnsAllShippingMethods(): void
    {
        $shippingMethod1 = $this->createShippingMethod('shipping-1');
        $shippingMethod2 = $this->createShippingMethod('shipping-2');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod1);
        $product = $this->createProduct('product-1');
        $shippingMethods = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]);

        $route = new ProductShippingCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock($shippingMethods, $context),
            $this->createProcessorMock(2),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $response = $route->shippingCostsByProduct($product->getId(), new Criteria(), $context);

        static::assertCount(2, $response->getShippingCosts());
        static::assertNotNull($response->getShippingCost($shippingMethod1->getId()));
        static::assertNotNull($response->getShippingCost($shippingMethod2->getId()));
    }

    public function testShippingCostsByProductSkipsShippingMethodsWithoutDelivery(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);
        $product = $this->createProduct('product-1');

        $route = new ProductShippingCostRoute(
            $this->createProductGatewayMock($product, $context),
            $this->createShippingMethodRepositoryMock(new ShippingMethodCollection([$shippingMethod]), $context, [$shippingMethod->getId()]),
            $this->createProcessorMock(1, new Cart('test')),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $response = $route->shippingCostsByProduct($product->getId(), new Criteria([$shippingMethod->getId()]), $context);

        static::assertCount(0, $response->getShippingCosts());
    }

    public function testShippingCostsByProductThrowsWhenProductCannotBeLoaded(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productId = 'product-1';

        $productGateway = $this->createMock(ProductGatewayInterface::class);
        $productGateway
            ->expects($this->once())
            ->method('get')
            ->with([$productId], static::isInstanceOf(SalesChannelContext::class))
            ->willReturn(new ProductCollection());

        $shippingMethodRepository = $this->createMock(EntityRepository::class);
        $shippingMethodRepository
            ->expects($this->never())
            ->method('search');

        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->never())
            ->method('process');

        $route = new ProductShippingCostRoute(
            $productGateway,
            $shippingMethodRepository,
            $processor,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(CartException::class);

        $route->shippingCostsByProduct($productId, new Criteria(), $context);
    }

    public function testPublishesExtension(): void
    {
        $productId = Uuid::randomHex();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();
        $response = new ShippingCostRouteResponse(new ShippingCostCollection());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-shipping-cost-route.shipping-costs-by-product.pre', static function (ProductShippingCostRouteExtension $extension) use ($productId, $criteria, $context, $response): void {
            static::assertSame(['productId' => $productId, 'criteria' => $criteria, 'salesChannelContext' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductShippingCostRoute(
            static::createStub(ProductGatewayInterface::class),
            static::createStub(EntityRepository::class),
            static::createStub(Processor::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->shippingCostsByProduct($productId, $criteria, $context));
    }

    private function createShippingMethod(string $id): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setName('Shipping ' . $id);
        $shippingMethod->setActive(true);

        return $shippingMethod;
    }

    private function createProduct(string $id): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);

        return $product;
    }

    private function createProductGatewayMock(ProductEntity $product, SalesChannelContext $context): ProductGatewayInterface
    {
        $productGateway = $this->createMock(ProductGatewayInterface::class);
        $productGateway
            ->expects($this->once())
            ->method('get')
            ->with([$product->getId()], $context)
            ->willReturn(new ProductCollection([$product]));

        return $productGateway;
    }

    /**
     * @param list<string>|null $expectedIds
     *
     * @return EntityRepository<ShippingMethodCollection>
     */
    private function createShippingMethodRepositoryMock(
        ShippingMethodCollection $shippingMethods,
        SalesChannelContext $context,
        ?array $expectedIds = null,
    ): EntityRepository {
        $searchResult = new EntitySearchResult(
            'shipping_method',
            $shippingMethods->count(),
            $shippingMethods,
            null,
            new Criteria($expectedIds),
            $context->getContext()
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria) use ($expectedIds): bool {
                    if ($expectedIds !== null) {
                        static::assertSame($expectedIds, $criteria->getIds());
                    }

                    return true;
                }),
                $context->getContext()
            )
            ->willReturn($searchResult);

        return $repository;
    }

    private function createProcessorMock(int $expectedCalls, ?Cart $processedCart = null): Processor
    {
        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->exactly($expectedCalls))
            ->method('process')
            ->with(
                static::isInstanceOf(Cart::class),
                static::isInstanceOf(SalesChannelContext::class),
                static::isInstanceOf(CartBehavior::class)
            )
            ->willReturn($processedCart ?? Generator::createCartWithDelivery());

        return $processor;
    }

    private function createCartWithDeliveries(ShippingMethodEntity $shippingMethod, DeliveryDate $deliveryDate, float ...$shippingCosts): Cart
    {
        $cart = new Cart('test');
        $deliveries = [];

        foreach ($shippingCosts as $shippingCost) {
            $deliveries[] = new Delivery(
                new DeliveryPositionCollection(),
                $deliveryDate,
                $shippingMethod,
                new ShippingLocation(new CountryEntity()),
                new CalculatedPrice($shippingCost, $shippingCost, new CalculatedTaxCollection(), new TaxRuleCollection())
            );
        }

        $cart->setDeliveries(new DeliveryCollection($deliveries));

        return $cart;
    }
}
