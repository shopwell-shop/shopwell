<?php declare(strict_types=1);

namespace Shopwell\Administration\Controller;

use Shopwell\Administration\Dashboard\TodayStatisticsService;
use Shopwell\Administration\Framework\Routing\AdministrationRouteScope;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\User\UserCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class TodayDashboardController
{
    /**
     * @param EntityRepository<UserCollection> $users
     */
    public function __construct(private readonly TodayStatisticsService $statistics, private readonly EntityRepository $users)
    {
    }

    #[Route(path: '/api/_admin/dashboard/today', name: 'api.admin.dashboard.today', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:read', 'order_transaction:read', 'order_customer:read', 'state_machine_history:read']], methods: ['GET'])]
    public function today(Context $context): JsonResponse
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class);
        }
        $userId = $source->getUserId();
        if ($userId === null) {
            throw ApiException::userNotLoggedIn();
        }
        $user = $this->users->search(new Criteria([$userId]), $context)->getEntities()->first();
        if ($user === null) {
            throw ApiException::userNotLoggedIn();
        }

        return new JsonResponse($this->statistics->load($context, $user->getTimeZone()));
    }
}
