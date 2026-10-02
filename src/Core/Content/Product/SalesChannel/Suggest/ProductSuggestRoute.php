<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Suggest;

use Shopwell\Core\Content\Product\Extension\ProductSuggestRouteExtension;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductSuggestRoute extends AbstractProductSuggestRoute
{
    public const STATE = 'suggest-route-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly ProductListingLoader $productListingLoader,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractProductSuggestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/search-suggest',
        name: 'store-api.search.suggest',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse
    {
        return $this->extensions->publish(
            name: ProductSuggestRouteExtension::NAME,
            extension: new ProductSuggestRouteExtension($request, $context, $criteria),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse
    {
        $result = $this->productListingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        return new ProductSuggestRouteResponse($result);
    }
}
