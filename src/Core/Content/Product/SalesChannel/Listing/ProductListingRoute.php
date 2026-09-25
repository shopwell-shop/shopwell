<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Listing;

use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopwell\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductListingRoute extends AbstractProductListingRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly ProductListingLoader $listingLoader,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductStreamBuilderInterface|AbstractProductStreamBuilder $productStreamBuilder,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $categoryId): string
    {
        return 'product-listing-' . $categoryId;
    }

    #[Route(
        path: '/store-api/product-listing/{categoryId}',
        name: 'store-api.product.listing',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($categoryId));

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('product-listing-route::loading');

        $categoryCriteria = new Criteria([$categoryId]);
        $categoryCriteria->setTitle('product-listing-route::category-loading');
        $categoryCriteria->addFields(['productAssignmentType', 'productStreamId']);
        $categoryCriteria->setLimit(1);

        /** @var ?PartialEntity */
        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->getEntities()->first();
        if (!$category) {
            throw ProductException::categoryNotFound($categoryId);
        }

        $criteria = $this->extensions->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: new ProductListingCriteriaExtension($criteria, $context, $categoryId),
            function: function ($criteria, $context, $categoryId) use ($category): Criteria {
                $this->extendCriteria($context, $criteria, $category);

                return $criteria;
            }
        );

        $entities = $this->listingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($entities);
        $result->addState(...$entities->getStates());

        $result->setStreamId($category->get('productStreamId'));

        return new ProductListingRouteResponse($result);
    }

    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, PartialEntity $category): void
    {
        $productAssignmentType = $category->get('productAssignmentType');
        $productStreamId = $category->get('productStreamId');

        if ($productAssignmentType === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM && \is_string($productStreamId) && $productStreamId !== '') {
            $this->cacheTagCollector->addTag(
                EntityCacheKeyGenerator::buildStreamTag($productStreamId)
            );

            $productStreamBuilder = $this->productStreamBuilder;
            if ($productStreamBuilder instanceof AbstractProductStreamBuilder) {
                $productStreamBuilder->enrichCriteria($criteria, $productStreamId, $salesChannelContext->getContext());
            } else {
                $criteria->addFilter(...$productStreamBuilder->buildFilters($productStreamId, $salesChannelContext->getContext()));
            }

            return;
        }

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $category->getId())
        );
    }
}
