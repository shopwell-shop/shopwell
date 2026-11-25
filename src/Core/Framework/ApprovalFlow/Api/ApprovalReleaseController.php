<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ApprovalReleaseController
{
    public function __construct(
        private readonly ApprovalDraftService $drafts,
        private readonly ApprovalReleaseService $releases,
        private readonly ApprovalBindingService $bindings,
    ) {
    }

    #[Route(path: '/api/_action/approval-flow/design/drafts/{definitionId}/publication-preview', name: 'api.action.approval_flow.design.publication_preview', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_release:create']], requirements: ['definitionId' => '[0-9a-f]{32}'], methods: [Request::METHOD_POST])]
    public function preview(string $definitionId, Request $request, Context $context): JsonResponse
    {
        $revision = $this->draftRevision($request);
        $draft = $this->drafts->read($definitionId, $context);

        return new JsonResponse(['contentHash' => $this->releases->preview($draft, $revision)]);
    }

    #[Route(path: '/api/_action/approval-flow/design/drafts/{definitionId}/publish', name: 'api.action.approval_flow.design.publish', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_release:create']], requirements: ['definitionId' => '[0-9a-f]{32}'], methods: [Request::METHOD_POST])]
    public function publish(string $definitionId, Request $request, Context $context): JsonResponse
    {
        $revision = $this->draftRevision($request);
        $hash = $request->getPayload()->getString('expectedHash');
        if (preg_match('/\A[0-9a-f]{64}\z/', $hash) !== 1) {
            throw ApprovalFlowException::invalidReleaseCommand('expectedHash');
        }

        return new JsonResponse($this->releases->publish($definitionId, $revision, $hash, $context));
    }

    #[Route(path: '/api/_action/approval-flow/design/releases/{releaseId}/activate', name: 'api.action.approval_flow.design.activate_release', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['approval_flow_binding:activate', 'approval_flow_binding:create']], requirements: ['releaseId' => '[0-9a-f]{32}'], methods: [Request::METHOD_POST])]
    public function activate(string $releaseId, Request $request, Context $context): JsonResponse
    {
        $payload = $request->getPayload();
        $revision = $payload->getInt('expectedRevision', -1);
        $version = $payload->getInt('expectedVersion', -1);
        if ($revision < 0 || $version < 0) {
            throw ApprovalFlowException::invalidReleaseCommand($revision < 0 ? 'expectedRevision' : 'expectedVersion');
        }
        $head = $this->bindings->activateRelease(
            $payload->getString('businessType'),
            $payload->getString('businessVersion'),
            $releaseId,
            $revision,
            $version,
            $context,
        );

        return new JsonResponse(['revision' => $head->getActiveRevision(), 'version' => $head->getVersion()]);
    }

    private function draftRevision(Request $request): int
    {
        $revision = $request->getPayload()->getInt('expectedRevision');
        if ($revision < 1) {
            throw ApprovalFlowException::invalidDraftRevision();
        }

        return $revision;
    }
}
