<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopwell\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopwell\Core\Content\Product\Extension\ProductPurchaseLimitRouteExtension;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
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
class ProductPurchaseLimitRoute extends AbstractProductPurchaseLimitRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractProductPurchaseLimitRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/purchase-limit',
        name: 'store-api.product.purchase-limit',
        methods: [Request::METHOD_GET],
        priority: 1, // keeping priority higher than in \Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute
    )]
    public function readProductsPurchaseLimit(Request $request, SalesChannelContext $context): ProductPurchaseLimitRouteResponse
    {
        return $this->extensions->publish(
            name: ProductPurchaseLimitRouteExtension::NAME,
            extension: new ProductPurchaseLimitRouteExtension($request, $context),
            function: $this->_readProductsPurchaseLimit(...),
        );
    }

    private function _readProductsPurchaseLimit(Request $request, SalesChannelContext $context): ProductPurchaseLimitRouteResponse
    {
        /** @var array<string> $ids */
        $ids = $request->query->all('ids');

        if ($ids === []) {
            throw ProductException::missingRequestParameter('ids');
        }

        $criteria = new Criteria($ids);
        $criteria->setTitle('product-purchase-limit-route');

        $criteria->addFields([
            'minPurchase',
            'maxPurchase',
            'purchaseSteps',
            'isCloseout',
            'stock',
        ]);

        $products = $this->productRepository->search($criteria, $context)->getEntities();

        $results = new ProductPurchaseLimitCollection();

        foreach ($products as $product) {
            $maxPurchase = $this->maxPurchaseCalculator->calculate($product, $context);
            $minPurchase = $product->get('minPurchase') ?? 1;
            $purchaseSteps = $product->get('purchaseSteps') ?? 1;
            $stock = $product->get('stock');

            $results->add(new ProductPurchaseLimit(
                $product->getId(),
                $minPurchase,
                $purchaseSteps,
                $maxPurchase,
                $stock,
            ));
        }

        return new ProductPurchaseLimitRouteResponse($results);
    }
}
