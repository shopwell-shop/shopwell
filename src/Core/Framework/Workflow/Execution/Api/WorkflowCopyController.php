<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyService;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\WorkflowCopyApiTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:access']])]
final readonly class WorkflowCopyController
{
    public function __construct(private WorkflowCopyService $copies, private WorkflowCopyPresenter $presenter)
    {
    }

    #[Route(path: '/api/_action/workflow/copy', name: 'api.workflow.execution.copy.received', methods: [Request::METHOD_GET])]
    public function received(Context $context, Request $request): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(max(1, min(100, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));
        $result = $this->copies->received($criteria, $context);

        return new JsonResponse([
            'data' => $this->presenter->presentMany($result->getEntities(), $context),
            'meta' => ['total' => $result->getTotal()],
        ]);
    }

    #[Route(path: '/api/_action/workflow/copy/{copyId}/read', name: 'api.workflow.execution.copy.read', methods: [Request::METHOD_POST])]
    public function markRead(Context $context, string $copyId): Response
    {
        $this->copies->markRead($copyId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
