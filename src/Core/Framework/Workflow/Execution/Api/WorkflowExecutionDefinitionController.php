<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only access to executable workflow definitions.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class WorkflowExecutionDefinitionController extends AbstractController
{
    /**
     * @param EntityRepository<WorkflowExecutionDefinitionCollection> $definitionRepository
     */
    public function __construct(
        private readonly ApprovalWorkflowService $flows,
        private readonly WorkflowExecutionDefinitionPresenter $presenter,
        private readonly EntityRepository $definitionRepository,
    ) {
    }

    #[Route(
        path: '/api/_action/workflow/definition',
        name: 'api.workflow.execution.definition.list',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow:read']],
        methods: [Request::METHOD_GET]
    )]
    public function list(Context $context, Request $request): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(max(1, min(100, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));
        $criteria->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING));

        $status = $request->query->get('status');

        if (\is_string($status) && $status !== '') {
            $criteria->addFilter(new EqualsFilter('status', $status));
        }

        $search = $request->query->get('search');

        if (\is_string($search) && $search !== '') {
            $criteria->addFilter(new ContainsFilter('name', $search));
        }

        $result = $this->definitionRepository->search($criteria, $context);

        /** @var WorkflowExecutionDefinitionCollection $definitions */
        $definitions = $result->getEntities();

        $data = [];

        foreach ($definitions as $definition) {
            // 列表不带图：一张图几十 KB，列表页只需要名称与状态
            $data[] = $this->presenter->present($definition, $context, false);
        }

        return new JsonResponse([
            'data' => $data,
            'meta' => ['total' => $result->getTotal()],
        ]);
    }

    #[Route(
        path: '/api/_action/workflow/definition/{definitionId}',
        name: 'api.workflow.execution.definition.detail',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow:read']],
        methods: [Request::METHOD_GET]
    )]
    public function detail(Context $context, string $definitionId): JsonResponse
    {
        $definition = $this->flows->loadDefinition($definitionId, $context);

        return new JsonResponse(['data' => $this->presenter->present($definition, $context)]);
    }
}
