<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * 待办 / 已办 / 同意 / 拒绝。
 *
 * ## 安全要点
 *
 * - `todo` / `done` 的用户 id **只从 Context 取**，不接受查询参数里传人；
 * - `approve` / `reject` 的归属校验在 {@see ApprovalWorkflowService::approve()} 里做
 *   （别人的任务返回 404，不暴露存在性）；
 * - 权限用 `workflow_task:decide`，但那只说明"这个人能审批"，
 *   不说明"这条任务是他的"——两层校验缺一不可。
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class WorkflowTaskController extends AbstractController
{
    public function __construct(
        private readonly ApprovalWorkflowService $flows,
        private readonly WorkflowTaskPresenter $presenter,
    ) {
    }

    #[Route(
        path: '/api/_action/workflow/task/todo',
        name: 'api.workflow.execution.task.todo',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:access']],
        methods: [Request::METHOD_GET]
    )]
    public function todo(Context $context, Request $request): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(max(1, min(100, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));

        $result = $this->flows->todo($this->flows->actorId($context), $criteria, $context);

        $tasks = array_values($result->getEntities()->getElements());

        return new JsonResponse([
            'data' => $this->presenter->presentMany($tasks, $context, withInstanceSummary: true),
            'meta' => ['total' => $result->getTotal()],
        ]);
    }

    #[Route(
        path: '/api/_action/workflow/task/done',
        name: 'api.workflow.execution.task.done',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:access']],
        methods: [Request::METHOD_GET]
    )]
    public function done(Context $context, Request $request): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(max(1, min(100, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));

        $result = $this->flows->done($this->flows->actorId($context), $criteria, $context);

        $tasks = array_values($result->getEntities()->getElements());
        $summaries = $this->presenter->instanceSummaries(array_map(static fn (WorkflowTaskHistoryEntity $task): string => $task->instanceId, $tasks), $context);
        $rows = [];

        foreach ($tasks as $hisTask) {
            $rows[] = [
                'id' => $hisTask->id,
                'taskId' => $hisTask->taskId,
                'instanceId' => $hisTask->instanceId,
                'nodeId' => $hisTask->nodeId,
                'nodeName' => $hisTask->nodeName,
                'decision' => $hisTask->decision,
                'comment' => $hisTask->comment,
                'completedAt' => $hisTask->completedAt?->format(\DateTimeInterface::ATOM),
                ...($summaries[$hisTask->instanceId] ?? []),
            ];
        }

        return new JsonResponse(['data' => $rows, 'meta' => ['total' => $result->getTotal()]]);
    }

    #[Route(
        path: '/api/_action/workflow/task/{taskId}/approve',
        name: 'api.workflow.execution.task.approve',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_task:decide']],
        methods: [Request::METHOD_POST]
    )]
    public function approve(Context $context, Request $request, string $taskId): Response
    {
        $comment = $request->getPayload()->all()['comment'] ?? null;

        $this->flows->approve($taskId, \is_string($comment) && trim($comment) !== '' ? $comment : null, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/workflow/task/{taskId}/reject',
        name: 'api.workflow.execution.task.reject',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_task:decide']],
        methods: [Request::METHOD_POST]
    )]
    public function reject(Context $context, Request $request, string $taskId): Response
    {
        $comment = $request->getPayload()->all()['comment'] ?? null;

        // 意见必填的校验在 ApprovalWorkflowService（门面统一做），这里只负责把原值传下去
        $this->flows->reject($taskId, \is_string($comment) ? $comment : null, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
