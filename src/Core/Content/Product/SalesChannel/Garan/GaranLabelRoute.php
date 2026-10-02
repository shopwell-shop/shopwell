<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Garan;

use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\Extension\GaranLabelRouteExtension;
use Shopwell\Core\Content\Product\Garan\GaranLabelResolver;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
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
class GaranLabelRoute extends AbstractGaranLabelRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractGaranLabelRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/garan-label',
        name: 'store-api.product.garan-label',
        methods: [Request::METHOD_GET]
    )]
    public function load(string $productId, SalesChannelContext $context): GaranLabelRouteResponse
    {
        return $this->extensions->publish(
            name: GaranLabelRouteExtension::NAME,
            extension: new GaranLabelRouteExtension($productId, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $productId, SalesChannelContext $context): GaranLabelRouteResponse
    {
        $product = $this->loadProduct($productId, $context);

        return new GaranLabelRouteResponse(
            $this->resolver->resolve($product, GaranLabelResolver::LABEL_TYPE_FULL),
            $this->resolver->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED),
        );
    }

    private function loadProduct(string $productId, SalesChannelContext $context): SalesChannelProductEntity
    {
        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-garan-label-route');
        $criteria->addAssociation('manufacturer');
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        if (!$product instanceof SalesChannelProductEntity) {
            throw ProductException::productNotFound($productId);
        }

        return $product;
    }
}
