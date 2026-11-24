<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalRequestQuery;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalRequestView;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api\ApprovalRequestControllerTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ApprovalRequestController
{
    public function __construct(private readonly ApprovalRequestQuery $queries)
    {
    }

    #[Route(path: '/api/_action/approval-flow/requests', name: 'api.action.approval_flow.request.search', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow:access']], methods: [Request::METHOD_GET])]
    public function search(Request $request, Context $context): JsonResponse
    {
        $view = $request->query->getEnum('view', ApprovalRequestView::class, ApprovalRequestView::Todo);
        $status = $request->query->has('status') ? $request->query->getEnum('status', ApprovalInstanceStatus::class) : null;
        $limit = $request->query->getInt('limit', 25);
        $offset = $request->query->getInt('offset', 0);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw ApprovalFlowException::invalidRequestQuery('Limit must be between 1 and 100 and offset must be nonnegative.');
        }

        return new JsonResponse($this->queries->search($view, $limit, $offset, $status, $context));
    }

    #[Route(path: '/api/_action/approval-flow/requests/{requestId}', name: 'api.action.approval_flow.request.detail', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow:access']], methods: [Request::METHOD_GET])]
    public function detail(string $requestId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($requestId)) {
            throw ApprovalFlowException::invalidRequestQuery('A valid approval request ID is required.');
        }

        return new JsonResponse($this->queries->detail($requestId, $context));
    }
}
