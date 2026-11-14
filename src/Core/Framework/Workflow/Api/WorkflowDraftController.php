<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Draft\Entity\WorkflowDraftEntity;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\WorkflowException;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowDraftPersistenceTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final readonly class WorkflowDraftController
{
    public function __construct(private WorkflowDraftStore $store)
    {
    }

    #[Route(path: '/api/_action/workflow/draft/{id}', name: 'api.action.workflow.draft.read', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:read']], methods: [Request::METHOD_GET])]
    public function read(string $id, Context $context): JsonResponse
    {
        return $this->respond($this->store->get($id, $context));
    }

    #[Route(path: '/api/_action/workflow/draft', name: 'api.action.workflow.draft.create', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:create']], methods: [Request::METHOD_POST])]
    public function create(Request $request, Context $context): JsonResponse
    {
        $body = $this->body($request);
        if (!\is_string($body->id ?? null)) {
            throw WorkflowException::invalidDocument('/id', 'Expected a UUID.');
        }
        [$name, $document] = $this->content($body);

        return $this->respond($this->store->create($body->id, $name, $document, $context), Response::HTTP_CREATED);
    }

    #[Route(path: '/api/_action/workflow/draft/{id}', name: 'api.action.workflow.draft.update', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:update']], methods: [Request::METHOD_PUT])]
    public function update(string $id, Request $request, Context $context): JsonResponse
    {
        $body = $this->body($request);
        [$name, $document] = $this->content($body);

        return $this->respond($this->store->update($id, $name, $document, $this->revision($body), $context));
    }

    #[Route(path: '/api/_action/workflow/draft/{id}', name: 'api.action.workflow.draft.delete', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:delete']], methods: [Request::METHOD_DELETE])]
    public function delete(string $id, Request $request, Context $context): Response
    {
        $this->store->delete($id, $this->revision($this->body($request)), $context);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function body(Request $request): \stdClass
    {
        try {
            $body = json_decode($request->getContent(), flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw WorkflowException::invalidDocument('/', 'Expected JSON.');
        }
        if (!$body instanceof \stdClass) {
            throw WorkflowException::invalidDocument('/', 'Expected an object.');
        }

        return $body;
    }

    /**
     * @return array{string, string}
     */
    private function content(\stdClass $body): array
    {
        if (!\is_string($body->name ?? null) || !($body->document ?? null) instanceof \stdClass) {
            throw WorkflowException::invalidDocument('/', 'Expected a name and document object.');
        }

        return [$body->name, json_encode($body->document, \JSON_THROW_ON_ERROR)];
    }

    private function revision(\stdClass $body): int
    {
        if (!\is_int($body->expectedRevision ?? null) || $body->expectedRevision < 1) {
            throw WorkflowException::invalidDocument('/expectedRevision', 'Expected a positive integer.');
        }

        return $body->expectedRevision;
    }

    private function respond(WorkflowDraftEntity $draft, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['id' => $draft->getId(), 'name' => $draft->getName(), 'revision' => $draft->getRevision(), 'document' => json_decode($draft->getDocument(), flags: \JSON_THROW_ON_ERROR)], $status);
    }
}
