<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], PlatformRequest::ATTRIBUTE_ACL => ['workflow:read']])]
final readonly class WorkflowController
{
    public function __construct(
        private WorkflowNodeTypeRegistry $types,
        private WorkflowDocumentCodec $codec,
        private WorkflowGraphDiagnostics $diagnostics,
    ) {
    }

    #[Route(path: '/api/_info/workflow-node-types.json', name: 'api.info.workflow_node_types', methods: [Request::METHOD_GET])]
    public function types(): JsonResponse
    {
        return new JsonResponse(['types' => $this->types->all()]);
    }

    #[Route(path: '/api/_action/workflow/diagnose', name: 'api.action.workflow.diagnose', methods: [Request::METHOD_POST])]
    public function diagnose(Request $request): JsonResponse
    {
        return new JsonResponse(['violations' => $this->diagnostics->analyze($this->codec->decode($request->getContent()))]);
    }
}
