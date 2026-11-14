<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
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
 * @internal
 *
 * 审批实例接口：发起、详情、我发起的、撤销。
 *
 * `GET /{instanceId}` 与 `GET /mine` 的顺序不能反：`/mine` 必须注册在参数路由**之前**，
 * 否则 `mine` 会被当成 `{instanceId}` 匹配掉，报"实例不存在"。
 * 这里把 `mine` 写在前面，路由按注册顺序匹配。
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

        $instanceId = $this->flows->start($definitionId, $formData, $title, $context);

        return new JsonResponse(['data' => ['id' => $instanceId]], Response::HTTP_CREATED);
    }

    /**
     * 我发起的。
     *
     * 只返回当前用户发起的，**不信任任何前端传的 initiatorId**。
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
        $criteria->addFilter(new EqualsFilter('initiatorId', $this->flows->actorId($context)));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(max(1, min(100, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));

        $status = $request->query->get('status');

        if (\is_string($status) && $status !== '') {
            // 前端传的是契约值（RUNNING/AGREE/...），库里存的是小写状态名，
            // 必须映射一次。直接拿契约值去过滤会永远查不到数据。
            $byContract = array_flip(WorkflowInstanceStates::CONTRACT_STATUS);

            if (isset($byContract[$status])) {
                $criteria->addFilter(new EqualsFilter('stateMachineState.technicalName', $byContract[$status]));
            }
        }

        $criteria->addAssociation('stateMachineState');
        $result = $this->instanceRepository->search($criteria, $context);
        $data = [];

        foreach ($result->getEntities() as $instance) {
            $data[] = $this->presenter->present($instance, $context, false);
        }

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
        $instance = $this->flows->instanceDetail($instanceId, $context, $this->flows->actorId($context));

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
