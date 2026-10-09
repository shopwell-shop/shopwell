<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\FindVariant;

use Shopwell\Core\Content\Product\Extension\FindProductVariantRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class FindProductVariantRoute extends AbstractFindProductVariantRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractFindProductVariantRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/find-variant',
        name: 'store-api.product.find-variant',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse
    {
        return $this->extensions->publish(
            name: FindProductVariantRouteExtension::NAME,
            extension: new FindProductVariantRouteExtension($productId, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse
    {
        $switchedGroup = RequestParamHelper::get($request, 'switchedGroup');

        $options = RequestParamHelper::get($request, 'options', []);

        foreach ($options as $optionId) {
            if (!\is_string($optionId)) {
                throw ProductException::invalidOptionsParameter();
            }
        }

        if (Feature::isActive('CACHE_REWORK')) {
            $this->cacheTagCollector->addTag(EntityCacheKeyGenerator::buildProductTag($productId));
        }

        $variantId = $this->searchForOptions($productId, $context, $options);

        if ($variantId !== null) {
            return new FindProductVariantRouteResponse(new FoundCombination($variantId, $options));
        }

        while (\count($options) > 1) {
            foreach ($options as $groupId => $_optionId) {
                if ($groupId !== $switchedGroup) {
                    unset($options[$groupId]);

                    break;
                }
            }

            $variantId = $this->searchForOptions($productId, $context, $options);

            if ($variantId) {
                return new FindProductVariantRouteResponse(new FoundCombination($variantId, $options));
            }
        }

        throw ProductException::variantNotFound($productId, $options);
    }

    /**
     * @param array<string> $options
     */
    private function searchForOptions(
        string $productId,
        SalesChannelContext $salesChannelContext,
        array $options
    ): ?string {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->setLimit(1);

        foreach ($options as $optionId) {
            $criteria->addFilter(new EqualsFilter('product.optionIds', $optionId));
        }

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $salesChannelContext->getSalesChannelId()
        )) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($salesChannelContext));
        }

        return $this->productRepository->searchIds($criteria, $salesChannelContext)->firstId();
    }
}
