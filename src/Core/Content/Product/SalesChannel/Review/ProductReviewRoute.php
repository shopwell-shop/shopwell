<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Review;

use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\Extension\ProductReviewRouteExtension;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductReviewRoute extends AbstractProductReviewRoute
{
    public const DEFAULT_MAX_LIMIT = 100;

    /**
     * @internal
     *
     * @param EntityRepository<ProductReviewCollection> $productReviewRepository
     */
    public function __construct(
        private readonly EntityRepository $productReviewRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
        private readonly int $maxLimit = self::DEFAULT_MAX_LIMIT,
        private readonly CompressedCriteriaDecoder $compressedCriteriaDecoder = new CompressedCriteriaDecoder(),
    ) {
    }

    public static function buildName(string $parentId): string
    {
        return EntityCacheKeyGenerator::buildProductTag($parentId);
    }

    public function getDecorated(): AbstractProductReviewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/reviews',
        name: 'store-api.product-review.list',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductReviewDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        return $this->extensions->publish(
            name: ProductReviewRouteExtension::NAME,
            extension: new ProductReviewRouteExtension(
                $productId,
                $request,
                $context,
                $this->applyConfiguredLimit($criteria, $context->getSalesChannelId(), $request),
            ),
            function: $this->_load(...),
        );
    }

    private function _load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        $salesChannelId = $context->getSalesChannelId();
        if (!$this->systemConfigService->getBool('core.listing.showReview', $salesChannelId)) {
            throw ProductException::reviewNotActive();
        }

        $this->cacheTagCollector->addTag(self::buildName($productId));

        $active = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
        if ($customer = $context->getCustomer()) {
            $active->addQuery(new EqualsFilter('customerId', $customer->getId()));
        }

        $criteria->setTitle('product-review-route');
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                $active,
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $result = $this->productReviewRepository->search($criteria, $context->getContext());

        return new ProductReviewRouteResponse($result);
    }

    private function applyConfiguredLimit(Criteria $criteria, string $salesChannelId, Request $request): Criteria
    {
        if (!$criteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST)) {
            return $criteria;
        }

        $reviewsPerPage = $this->systemConfigService->getInt('core.listing.reviewsPerPage', $salesChannelId);
        $reviewsPerPage = min($reviewsPerPage, $this->maxLimit);
        if ($reviewsPerPage <= 0) {
            return $criteria;
        }

        // The offset was derived from the max limit while resolving the page, so
        // recompute it for the configured page size to keep pagination consistent.
        $currentLimit = $criteria->getLimit();
        $currentOffset = $criteria->getOffset();
        if ($currentLimit && $currentOffset) {
            $page = intdiv($currentOffset, $currentLimit) + 1;
            $criteria->setOffset($reviewsPerPage * ($page - 1));
        }

        $criteria->setLimit($reviewsPerPage);
        if (!$this->hasExplicitTotalCountMode($request)) {
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        }

        $criteria->removeState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        return $criteria;
    }

    private function hasExplicitTotalCountMode(Request $request): bool
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            $payload = $request->query->has('_criteria')
                ? $this->compressedCriteriaDecoder->decode((string) $request->query->get('_criteria'))
                : $request->query->all();
        } else {
            $payload = $request->request->all();
        }

        return isset($payload['total-count-mode']);
    }
}
