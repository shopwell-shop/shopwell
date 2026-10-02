<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Content\Product\Extension\ProductListRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductListRoute extends AbstractProductListRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly SalesChannelRepository $productRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractProductListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product',
        name: 'store-api.product.search',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Criteria $criteria, SalesChannelContext $context): ProductListResponse
    {
        return $this->extensions->publish(
            name: ProductListRouteExtension::NAME,
            extension: new ProductListRouteExtension($criteria, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Criteria $criteria, SalesChannelContext $context): ProductListResponse
    {
        return new ProductListResponse($this->productRepository->search($criteria, $context));
    }
}
