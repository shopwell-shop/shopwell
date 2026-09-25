<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Api;

use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\FilterServiceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class PromotionActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LineItemGroupServiceRegistry $serviceRegistry,
        private readonly FilterServiceRegistry $filterServiceRegistry
    ) {
    }

    #[Route(
        path: '/api/_action/promotion/setgroup/packager',
        name: 'api.action.promotion.setgroup.packager',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getSetGroupPackagers(): JsonResponse
    {
        $packagerKeys = [];

        /** @var LineItemGroupPackagerInterface $packager */
        foreach ($this->serviceRegistry->getPackagers() as $packager) {
            $packagerKeys[] = $packager->getKey();
        }

        return new JsonResponse($packagerKeys);
    }

    #[Route(
        path: '/api/_action/promotion/setgroup/sorter',
        name: 'api.action.promotion.setgroup.sorter',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getSetGroupSorters(): JsonResponse
    {
        $sorterKeys = [];

        /** @var LineItemGroupSorterInterface $sorter */
        foreach ($this->serviceRegistry->getSorters() as $sorter) {
            $sorterKeys[] = $sorter->getKey();
        }

        return new JsonResponse($sorterKeys);
    }

    #[Route(
        path: '/api/_action/promotion/discount/picker',
        name: 'api.action.promotion.discount.picker',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getDiscountFilterPickers(): JsonResponse
    {
        $pickerKeys = [];

        foreach ($this->filterServiceRegistry->getPickers() as $picker) {
            $pickerKeys[] = $picker->getKey();
        }

        return new JsonResponse($pickerKeys);
    }
}
