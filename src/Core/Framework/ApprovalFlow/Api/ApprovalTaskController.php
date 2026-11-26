<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api\ApprovalTaskControllerTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ApprovalTaskController
{
    public function __construct(private readonly ApprovalInstanceService $instances)
    {
    }

    #[Route(path: '/api/_action/approval-flow/instances/{instanceId}/tasks/{taskId}/decide', name: 'api.action.approval_flow.task.decide', defaults: [PlatformRequest::ATTRIBUTE_ACL => [ApprovalInstanceService::DECIDE_PRIVILEGE]], methods: [Request::METHOD_POST])]
    public function decide(
        string $instanceId,
        string $taskId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ApprovalTaskDecisionRequest $payload,
        Context $context,
    ): JsonResponse {
        return new JsonResponse($this->instances->decide(
            $instanceId,
            $taskId,
            $payload->commandId,
            $payload->expectedVersion,
            $payload->outcome,
            $payload->reason,
            $context,
        ));
    }

    #[Route(path: '/api/_action/approval-flow/instances/{instanceId}/tasks/{taskId}/return-targets', name: 'api.action.approval_flow.task.return_targets', defaults: [PlatformRequest::ATTRIBUTE_ACL => [ApprovalInstanceService::DECIDE_PRIVILEGE]], methods: [Request::METHOD_GET])]
    public function returnTargets(string $instanceId, string $taskId, Context $context): JsonResponse
    {
        return new JsonResponse(['data' => $this->instances->returnTargets($instanceId, $taskId, $context)]);
    }

    #[Route(path: '/api/_action/approval-flow/instances/{instanceId}/tasks/{taskId}/return', name: 'api.action.approval_flow.task.return', defaults: [PlatformRequest::ATTRIBUTE_ACL => [ApprovalInstanceService::DECIDE_PRIVILEGE]], methods: [Request::METHOD_POST])]
    public function returnApproval(
        string $instanceId,
        string $taskId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ApprovalTaskReturnRequest $payload,
        Context $context,
    ): JsonResponse {
        return new JsonResponse($this->instances->returnApproval(
            $instanceId,
            $taskId,
            $payload->commandId,
            $payload->expectedVersion,
            $payload->targetExecutionId,
            $payload->reason,
            $context,
        ));
    }
}
