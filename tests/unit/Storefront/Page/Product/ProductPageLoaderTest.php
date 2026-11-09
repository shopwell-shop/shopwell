<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\Product\ProductPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPageLoader::class)]
class ProductPageLoaderTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['BREADCRUMB_REWORK'])]
    public function testExplicitBreadcrumbOptOutWinsWithMajorActive(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $context = $this->getSalesChannelContext();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $page = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getReviewTranslation(),
            seoCategory: $category,
            breadcrumbBuilder: $breadcrumbBuilder,
        )->load($request, $context);

        static::assertNull($page->getBreadcrumb());
    }

    public function testItLoadsStructuredDataReviewsForJsonLd(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $review = new ProductReviewEntity();
        $review->setId(Uuid::randomHex());
        $review->setTitle('Great product');
        $review->setContent('Really changed my life');
        $review->setPoints(5);
        $review->setStatus(true);

        $reviewCollection = new ProductReviewCollection([$review]);

        $entityResult = new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            1,
            $reviewCollection,
            new AggregationResultCollection([
                new TermsResult('ratingMatrix', []),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock
            ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
            ->method('search')
            ->willReturn($entityResult);

        $productPageLoader = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getReviewTranslation(),
            $reviewRepositoryMock
        );

        $page = $productPageLoader->load($request, $salesChannelContext);

        if (Feature::isActive('JSON_LD_DATA')) {
            $reviewData = $page->getStructuredDataReviews();
            static::assertNotNull($reviewData);
            static::assertSame(1, $reviewData->getTotal());
            static::assertSame($productId, $reviewData->getProductId());

            $loadedReview = $reviewData->getEntities()->first();
            static::assertNotNull($loadedReview);
            static::assertSame('Great product', $loadedReview->getTitle());
        } else {
            static::assertNull($page->getStructuredDataReviews());
        }
    }

    public function testItSkipsStructuredDataReviewsWhenReviewsAreDisabled(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock->expects($this->never())->method('search');

        $systemConfigMock = static::createStub(SystemConfigService::class);
        $systemConfigMock->method('getBool')->willReturn(false);

        $productPageLoader = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getReviewTranslation(),
            $reviewRepositoryMock,
            $systemConfigMock,
        );

        $page = $productPageLoader->load($request, $salesChannelContext);

        static::assertNull($page->getStructuredDataReviews());
    }

    public function testItSetsEmptyStructuredDataReviewsWhenNoReviewsExist(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $entityResult = new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            0,
            new ProductReviewCollection([]),
            new AggregationResultCollection([
                new TermsResult('ratingMatrix', []),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock
            ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
            ->method('search')
            ->willReturn($entityResult);

        $productPageLoader = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getReviewTranslation(),
            $reviewRepositoryMock
        );

        $page = $productPageLoader->load($request, $salesChannelContext);

        if (Feature::isActive('JSON_LD_DATA')) {
            $reviewData = $page->getStructuredDataReviews();
            static::assertNotNull($reviewData);
            static::assertSame(0, $reviewData->getTotal());
            static::assertSame(0, $reviewData->getMatrix()->getTotalReviewCount());
        } else {
            static::assertNull($page->getStructuredDataReviews());
        }
    }

    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     * @param EntityRepository<ProductReviewCollection>|null $reviewRepository
     */
    private function getProductPageLoaderWithProduct(
        string $productId,
        array $reviews,
        ?EntityRepository $reviewRepository = null,
        ?SystemConfigService $systemConfigService = null,
        ?CategoryEntity $seoCategory = null,
        ?CategoryBreadcrumbBuilder $breadcrumbBuilder = null,
    ): ProductPageLoader {
        $product = $this->getProductWithReviews($productId, $reviews);

        if ($seoCategory !== null) {
            $product->setSeoCategory($seoCategory);
        }

        $product->setProductNumber($productId);

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category')
            ->addAssociation('media.media')
            ->addAssociation('openGraphMedia');

        $criteria->getAssociation('media')->addSorting(
            new FieldSorting('position')
        );

        $productDetailRouteMock = static::createStub(ProductDetailRoute::class);
        $productDetailRouteMock
            ->method('load')
            ->willReturn(new ProductDetailRouteResponse($product, null));

        if ($reviewRepository === null) {
            $entityResult = new EntitySearchResult(
                ProductReviewDefinition::ENTITY_NAME,
                0,
                new ProductReviewCollection([]),
                new AggregationResultCollection([new TermsResult('ratingMatrix', [])]),
                new Criteria(),
                Context::createDefaultContext()
            );
            $reviewRepository = $this->createMock(EntityRepository::class);
            $reviewRepository
                ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
                ->method('search')
                ->willReturn($entityResult);
        }

        if ($systemConfigService === null) {
            $systemConfigService = static::createStub(SystemConfigService::class);
            // Default: reviews are enabled so the repository is actually called.
            $systemConfigService->method('getBool')->willReturn(true);
        }

        return new ProductPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(EventDispatcherInterface::class),
            $productDetailRouteMock,
            $reviewRepository,
            $systemConfigService,
            $breadcrumbBuilder ?? static::createStub(CategoryBreadcrumbBuilder::class)
        );
    }

    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     */
    private function getProductWithReviews(string $productId, array $reviews): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        // set reviews
        $product->setTranslated($reviews);

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }

    /**
     * @return array<string, array<string, array<string, array<string, array<string, string>>>>>
     */
    private function getReviewTranslation(): array
    {
        return [
            'data' => [
                'reviews' => [
                    'elements' => [
                        'myReviewElement' => [
                            'title' => 'myReviewTitle',
                            'content' => 'this product changed my life',
                        ],
                    ],
                ],
            ],
        ];
    }
}
