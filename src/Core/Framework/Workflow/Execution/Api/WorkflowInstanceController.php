<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Instance endpoints; register /mine before the parameter route so it is not matched as an instance ID.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class WorkflowInstanceController extends AbstractController
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instanceRepository
     */
    public function __construct(
        private readonly ApprovalWorkflowService $flows,
        private readonly WorkflowInstancePresenter $presenter,
        private readonly EntityRepository $instanceRepository,
        private readonly WorkflowService $workflows,
    ) {
    }

    #[Route(
        path: '/api/_action/workflow/instance',
        name: 'api.workflow.execution.instance.start',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:start']],
        methods: [Request::METHOD_POST]
    )]
    public function start(Context $context, Request $request): JsonResponse
    {
        $body = $request->getPayload()->all();

        $definitionId = \is_string($body['definitionId'] ?? null) ? $body['definitionId'] : '';
        $formData = \is_array($body['formData'] ?? null) ? $body['formData'] : [];
        $title = \is_string($body['title'] ?? null) ? $body['title'] : '';

        $instanceId = $this->workflows->start($definitionId, $formData, $title, $context);

        return new JsonResponse(['data' => ['id' => $instanceId]], Response::HTTP_CREATED);
    }

    /**
     * Return instances started by the current user; ignore client-supplied initiator IDs.
     */
    #[Route(
        path: '/api/_action/workflow/instance/mine',
        name: 'api.workflow.execution.instance.mine',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:access']],
        methods: [Request::METHOD_GET]
    )]
    public function mine(Context $context, Request $request): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addFilter(new EqualsFilter('initiatorId', $this->workflows->actorId($context)));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(max(1, min(100, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));

        $status = $request->query->get('status');

        if (\is_string($status) && $status !== '') {
            // Map contract statuses to stored state names before filtering.
            $byContract = array_flip(WorkflowInstanceStates::CONTRACT_STATUS);

            if (isset($byContract[$status])) {
                $criteria->addFilter(new EqualsFilter('stateMachineState.technicalName', $byContract[$status]));
            }
        }

        $criteria->addAssociation('stateMachineState');
        $result = $this->instanceRepository->search($criteria, $context);
        $data = $this->presenter->presentMany($result->getEntities(), $context);

        return new JsonResponse(['data' => $data, 'meta' => ['total' => $result->getTotal()]]);
    }

    #[Route(
        path: '/api/_action/workflow/instance/{instanceId}',
        name: 'api.workflow.execution.instance.detail',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:access']],
        methods: [Request::METHOD_GET]
    )]
    public function detail(Context $context, string $instanceId): JsonResponse
    {
        $instance = $this->flows->instanceDetail($instanceId, $context, $this->workflows->actorId($context));

        return new JsonResponse(['data' => $this->presenter->present($instance, $context, true)]);
    }

    #[Route(
        path: '/api/_action/workflow/instance/{instanceId}/cancel',
        name: 'api.workflow.execution.instance.cancel',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:start']],
        methods: [Request::METHOD_POST]
    )]
    public function cancel(Context $context, Request $request, string $instanceId): Response
    {
        $reason = $request->getPayload()->all()['reason'] ?? null;

        $this->flows->cancel($instanceId, \is_string($reason) ? $reason : '', $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
