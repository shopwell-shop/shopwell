<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisherRegistry;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowPublicationTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], PlatformRequest::ATTRIBUTE_ACL => ['workflow:read']])]
final readonly class WorkflowPublicationController
{
    public function __construct(private WorkflowReleaseStore $store, private WorkflowPublisherRegistry $publishers, private WorkflowInputSchema $forms)
    {
    }

    #[Route(path: '/api/_action/workflow/publishers', name: 'api.action.workflow.publishers', methods: [Request::METHOD_GET])]
    public function publishers(Context $context): JsonResponse
    {
        $data = [];
        foreach ($this->publishers->all() as $publisher) {
            $allowed = $context->isAllowed('workflow:publish');
            foreach ($publisher->privileges() as $privilege) {
                $allowed = $allowed && $context->isAllowed($privilege);
            }
            $data[] = ['name' => $publisher->name(), 'labelKey' => $publisher->labelKey(), 'canPublish' => $allowed];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_action/workflow/draft/{id}/publish', name: 'api.action.workflow.publish', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:read', 'workflow:publish']], methods: [Request::METHOD_POST])]
    public function publish(string $id, Request $request, Context $context): JsonResponse
    {
        $body = $request->getPayload()->all();
        if (!\is_int($body['expectedRevision'] ?? null) || $body['expectedRevision'] < 1 || !\is_string($body['publisher'] ?? null) || $body['publisher'] === '') {
            throw WorkflowException::invalidDocument('/', 'Expected a positive revision and publisher name.');
        }
        $release = $this->store->publish($id, $body['expectedRevision'], $body['publisher'], $context);

        return new JsonResponse($this->present($release), Response::HTTP_CREATED);
    }

    #[Route(path: '/api/_action/workflow/releases', name: 'api.action.workflow.releases', methods: [Request::METHOD_GET])]
    public function list(Request $request, Context $context): JsonResponse
    {
        $draftId = $request->query->get('draftId');
        $result = $this->store->list(\is_string($draftId) ? $draftId : null, $request->query->getInt('limit', 25), $request->query->getInt('offset'), $context);
        $data = [];
        foreach ($result->getEntities() as $release) {
            $data[] = $this->present($release);
        }

        return new JsonResponse(['data' => $data, 'total' => $result->getTotal()]);
    }

    #[Route(path: '/api/_action/workflow/release/{id}', name: 'api.action.workflow.release', methods: [Request::METHOD_GET])]
    public function read(string $id, Context $context): JsonResponse
    {
        $release = $this->store->get($id, $context);

        return new JsonResponse([...$this->present($release), 'document' => json_decode($release->getDocument(), flags: \JSON_THROW_ON_ERROR)]);
    }

    #[Route(path: '/api/_action/workflow/request-catalogue', name: 'api.action.workflow.request_catalogue', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:read', 'workflow_instance:start']], methods: [Request::METHOD_GET])]
    public function catalogue(Request $request, Context $context): JsonResponse
    {
        $result = $this->store->catalogue($request->query->getInt('limit', 25), $request->query->getInt('offset'), $context);
        $data = [];
        foreach ($result['releases'] as $release) {
            $document = json_decode($release->getDocument(), true, flags: \JSON_THROW_ON_ERROR);
            $start = array_values(array_filter($document['nodes'], static fn (array $node): bool => $node['type'] === DocumentNodeTypes::START))[0] ?? [];
            $data[] = [...$this->present($release), 'requestAction' => $start['config']['requestAction'] ?? $release->getPublisher()];
        }

        return new JsonResponse(['data' => $data, 'total' => $result['total']]);
    }

    #[Route(path: '/api/_action/workflow/form-options/{source}', name: 'api.action.workflow.form_options', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['workflow:read']], methods: [Request::METHOD_GET])]
    public function options(string $source, Context $context): JsonResponse
    {
        return new JsonResponse(['data' => $this->forms->options($source, $context)]);
    }

    /**
     * @return array{id:string,draftId:string,revision:int,name:string,publisher:string,createdAt:?string}
     */
    private function present(WorkflowReleaseEntity $release): array
    {
        return ['id' => $release->getId(), 'draftId' => $release->getDraftId(), 'revision' => $release->getRevision(), 'name' => $release->getName(), 'publisher' => $release->getPublisher(), 'createdAt' => $release->getCreatedAt()?->format(\DateTimeInterface::ATOM)];
    }
}
