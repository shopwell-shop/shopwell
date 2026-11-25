<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalDesignCatalog;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api\ApprovalDesignControllerTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ApprovalDesignController
{
    public function __construct(
        private readonly ApprovalDesignCatalog $catalog,
        private readonly GraphDiagnostics $diagnostics,
    ) {
    }

    #[Route(path: '/api/_action/approval-flow/design/catalog', name: 'api.action.approval_flow.design.catalog', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_definition:read']], methods: [Request::METHOD_GET])]
    public function catalog(): JsonResponse
    {
        return new JsonResponse($this->catalog->read());
    }

    #[Route(path: '/api/_action/approval-flow/design/diagnose', name: 'api.action.approval_flow.design.diagnose', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_definition:read']], methods: [Request::METHOD_POST])]
    public function diagnose(Request $request): JsonResponse
    {
        $diagnostics = $this->diagnostics->analyze(new DraftGraph($request->getPayload()->getString('graphJson')));
        $violations = [];
        foreach ($diagnostics->violations as $violation) {
            $violations[] = ['category' => $violation->category->value, 'code' => $violation->code, 'path' => $violation->path, 'message' => $violation->message];
        }

        return new JsonResponse($violations);
    }
}
