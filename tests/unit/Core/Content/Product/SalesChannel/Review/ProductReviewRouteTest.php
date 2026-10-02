<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopwell\Core\Content\Product\Extension\ProductReviewRouteExtension;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewRoute::class)]
class ProductReviewRouteTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<ProductReviewCollection>
     */
    private Stub&EntityRepository $repository;

    private StaticSystemConfigService $config;

    private CacheTagCollector&Stub $cacheTagCollector;

    private ProductReviewRoute $route;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->config = new StaticSystemConfigService([
            'test' => [
                'core.listing.showReview' => true,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewNotActive' => [
                'core.listing.showReview' => false,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
        ]);

        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);

        $this->route = $this->createRoute();
    }

    public function testLoad(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->once())->method('getCustomer')->willReturn($customer);
        $salesChannelContext->expects($this->exactly(1))->method('getSalesChannelId')->willReturn('test');
        $salesChannelContext->expects($this->exactly(1))->method('getContext')->willReturn($context);

        $expectedCriteria = new Criteria();
        $expectedCriteria->setTitle('product-review-route');
        $expectedCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('status', true),
                    new EqualsFilter('customerId', $customer->getId()),
                ]),
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->with($expectedCriteria, $context);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with($this->route::buildName($productId));

        $this->createRoute($repository, $cacheTagCollector)->load(
            $productId,
            new Request(),
            $salesChannelContext,
            new Criteria(),
        );
    }

    public function testLoadReviewDeactivated(): void
    {
        $this->expectExceptionObject(ProductException::reviewNotActive());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->exactly(1))->method('getSalesChannelId')->willReturn('testReviewNotActive');

        $this->route->load(
            Uuid::randomHex(),
            new Request(),
            $salesChannelContext,
            new Criteria(),
        );
    }

    public function testPublishesExtension(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductReviewRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-review-route.load.pre', static function (ProductReviewRouteExtension $extension) use ($productId, $request, $context, $criteria, $response): void {
            static::assertSame(['productId' => $productId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductReviewRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($productId, $request, $context, $criteria));
    }

    /**
     * @param (EntityRepository<ProductReviewCollection>&MockObject)|null $repository
     */
    private function createRoute(
        ?EntityRepository $repository = null,
        ?CacheTagCollector $cacheTagCollector = null,
    ): ProductReviewRoute {
        return new ProductReviewRoute(
            $repository ?? $this->repository,
            $this->config,
            $cacheTagCollector ?? $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
