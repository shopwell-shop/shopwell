<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ScheduledTaskController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly TaskScheduler $taskScheduler)
    {
    }

    #[Route(path: '/api/_action/scheduled-task/run', name: 'api.action.scheduled-task.run', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:queue:process']], methods: ['POST'])]
    public function runScheduledTasks(): JsonResponse
    {
        $this->taskScheduler->queueScheduledTasks();

        return new JsonResponse(['message' => 'Success']);
    }

    #[Route(path: '/api/_action/scheduled-task/min-run-interval', name: 'api.action.scheduled-task.min-run-interval', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['scheduled_task:read']], methods: ['GET'])]
    public function getMinRunInterval(): JsonResponse
    {
        return new JsonResponse(['minRunInterval' => $this->taskScheduler->getMinRunInterval()]);
    }
}
