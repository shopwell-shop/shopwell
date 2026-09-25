<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Api;

use Shopwell\Core\Content\Product\ProductTypeRegistry;
use Shopwell\Core\Content\Product\Util\VariantCombinationLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ProductActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly VariantCombinationLoader $combinationLoader,
        private readonly ProductTypeRegistry $productTypeRegistry
    ) {
    }

    #[Route(path: '/api/_action/product/{productId}/combinations', name: 'api.action.product.combinations', methods: ['GET'])]
    public function getCombinations(string $productId, Context $context): JsonResponse
    {
        return new JsonResponse(
            $this->combinationLoader->load($productId, $context)
        );
    }

    #[Route(path: '/api/_action/product/types', name: 'api.action.product.types', methods: ['GET'])]
    public function getProductTypes(): JsonResponse
    {
        return new JsonResponse($this->productTypeRegistry->getTypes());
    }
}
