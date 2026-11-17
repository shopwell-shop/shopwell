<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Task list and decision endpoints; derive the actor from Context and enforce task ownership as well as ACL permissions.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class WorkflowTaskController extends AbstractController
{
    public function __construct(
        private readonly ApprovalWorkflowService $flows,
        private readonly WorkflowTaskPresenter $presenter,
        private readonly WorkflowService $workflows,
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

        $result = $this->flows->todo($this->workflows->actorId($context), $criteria, $context);

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

        $result = $this->flows->done($this->workflows->actorId($context), $criteria, $context);

        $tasks = array_values($result->getEntities()->getElements());
        $summaries = $this->presenter->instanceSummaries(array_map(static fn (WorkflowTaskHistoryEntity $task): string => $task->instanceId, $tasks), $context);
        $rows = [];

        foreach ($tasks as $hisTask) {
            $rows[] = [
                'id' => $hisTask->id,
                'taskId' => $hisTask->taskId,
                'round' => $hisTask->round,
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
        $payload = $request->getPayload()->all();
        $comment = $payload['comment'] ?? null;
        $formData = \array_key_exists('formData', $payload) ? $payload['formData'] : [];
        $version = $payload['formVersion'] ?? null;
        if (!\is_array($formData) || ($formData !== [] && array_is_list($formData))
            || ($version !== null && (!\is_int($version) || $version < 0))) {
            throw WorkflowExecutionException::invalidGraph('Approval requires a formData object and a nonnegative integer formVersion.');
        }

        $this->flows->approve($taskId, \is_string($comment) && trim($comment) !== '' ? $comment : null, $context, $formData, $version);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/workflow/task/{taskId}/participation',
        name: 'api.workflow.execution.task.participation',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_task:decide']],
        methods: [Request::METHOD_POST]
    )]
    public function participate(Context $context, Request $request, string $taskId): Response
    {
        $payload = $request->getPayload()->all();
        $this->flows->participate(
            $taskId,
            \is_string($payload['action'] ?? null) ? $payload['action'] : '',
            \is_string($payload['targetUserId'] ?? null) ? $payload['targetUserId'] : '',
            \is_string($payload['comment'] ?? null) ? $payload['comment'] : '',
            $context,
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/workflow/task/{taskId}/operation',
        name: 'api.workflow.execution.task.operation',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_task:decide']],
        methods: [Request::METHOD_POST]
    )]
    public function operation(Context $context, Request $request, string $taskId): Response
    {
        $input = $request->getPayload()->all();
        $payload = \array_key_exists('payload', $input) ? $input['payload'] : [];
        if (!\is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw WorkflowExecutionException::invalidGraph('Operation payload must be an object.');
        }

        $this->flows->executeOperation(
            $taskId,
            \is_string($input['action'] ?? null) ? $input['action'] : '',
            $payload,
            \is_string($input['comment'] ?? null) ? $input['comment'] : '',
            $context,
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/workflow/task/{taskId}/return',
        name: 'api.workflow.execution.task.return',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_task:decide']],
        methods: [Request::METHOD_POST]
    )]
    public function returnToInitiator(Context $context, Request $request, string $taskId): Response
    {
        $comment = $request->getPayload()->all()['comment'] ?? null;
        $this->flows->returnToInitiator($taskId, \is_string($comment) ? $comment : '', $context);

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

        // Pass the comment unchanged; ApprovalWorkflowService validates required comments.
        $this->flows->reject($taskId, \is_string($comment) ? $comment : null, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
