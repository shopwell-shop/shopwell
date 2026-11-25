<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnosticsReport;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraft;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalDesignCatalog;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api\ApprovalDesignControllerTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ApprovalDesignController
{
    public function __construct(
        private readonly ApprovalDesignCatalog $catalog,
        private readonly GraphDiagnostics $diagnostics,
        private readonly ApprovalDraftService $drafts,
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

        return new JsonResponse($this->violations($diagnostics));
    }

    #[Route(path: '/api/_action/approval-flow/design/drafts', name: 'api.action.approval_flow.design.create_draft', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_definition:create', 'approval_flow_draft:create']], methods: [Request::METHOD_POST])]
    public function createDraft(Request $request, Context $context): JsonResponse
    {
        $payload = $request->getPayload();
        $name = trim($payload->getString('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            throw ApprovalFlowException::invalidGraph('/meta/name', 'A flow name of up to 255 characters is required.');
        }

        $draft = $this->drafts->create($name, $payload->getString('graphJson'), $context);

        return $this->draftResponse($draft, Response::HTTP_CREATED);
    }

    #[Route(path: '/api/_action/approval-flow/design/drafts/{definitionId}', name: 'api.action.approval_flow.design.save_draft', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_draft:update']], requirements: ['definitionId' => '[0-9a-f]{32}'], methods: [Request::METHOD_PATCH])]
    public function saveDraft(string $definitionId, Request $request, Context $context): JsonResponse
    {
        $payload = $request->getPayload();
        $revision = $payload->getInt('expectedRevision');
        if ($revision < 1) {
            throw ApprovalFlowException::invalidDraftRevision();
        }

        $draft = $this->drafts->save($definitionId, $revision, $payload->getString('graphJson'), $context);

        return $this->draftResponse($draft, Response::HTTP_OK);
    }

    private function draftResponse(ApprovalDraft $draft, int $status): JsonResponse
    {
        return new JsonResponse([
            'definitionId' => $draft->definitionId,
            'revision' => $draft->revision,
            'graphJson' => $draft->graph->json,
            'diagnostics' => $this->violations($draft->diagnostics),
        ], $status);
    }

    /**
     * @return list<array{category: string, code: string, path: string, message: string}>
     */
    private function violations(GraphDiagnosticsReport $report): array
    {
        return array_map(static fn (GraphViolation $violation): array => [
            'category' => $violation->category->value,
            'code' => $violation->code,
            'path' => $violation->path,
            'message' => $violation->message,
        ], $report->violations);
    }
}
