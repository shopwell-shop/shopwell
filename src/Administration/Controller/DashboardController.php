<?php declare(strict_types=1);

namespace Shopwell\Administration\Controller;

use Shopwell\Administration\Dashboard\OrderAmountService;
use Shopwell\Administration\Framework\Routing\AdministrationRouteScope;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class DashboardController extends AbstractController
{
    public function __construct(private readonly OrderAmountService $orderAmountService)
    {
    }

    #[Route(path: '/api/_admin/dashboard/order-amount/{since}', name: 'api.admin.dashboard.order-amount', methods: ['GET'])]
    public function orderAmount(string $since, Request $request, Context $context): JsonResponse
    {
        $paid = $request->query->getBoolean('paid', true);

        $timezone = $request->query->get('timezone', 'UTC');

        $amount = $this->orderAmountService->load($context, $since, $paid, $timezone);

        return new JsonResponse(['statistic' => $amount]);
    }
}
