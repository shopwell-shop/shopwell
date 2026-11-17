<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\Assignee\PositionAssigneeResolverTest
 */
#[Package('framework')]
final readonly class PositionAssigneeResolver implements AssigneeResolverInterface
{
    /**
     * @param EntityRepository<UserPositionCollection> $positions
     */
    public function __construct(
        private EntityRepository $positions,
        private OrganizationAssigneeLookup $organizations,
        private OrganizationAssigneeContext $assigneeContext,
    ) {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::POSITION;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $positionIds = $node->property('assigneeValue');
        if (!\is_array($positionIds) || !array_is_list($positionIds) || $positionIds === [] || \count($positionIds) > 50) {
            throw WorkflowExecutionException::noAssignee($node->id, $this->getAssigneeType());
        }
        foreach ($positionIds as $positionId) {
            if (!\is_string($positionId) || !Uuid::isValid($positionId)) {
                throw WorkflowExecutionException::noAssignee($node->id, $this->getAssigneeType());
            }
        }
        $departmentId = $this->assigneeContext->departmentId($instance);
        if ($departmentId === null) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('positionId', $positionIds),
            new EqualsFilter('organizationUnitId', $departmentId),
        );
        $criteria->addAssociation('position');
        $criteria->addAssociation('user.aclRoles');
        $criteria->addAssociation('organizationUnit.organization');
        $criteria->addSorting(new FieldSorting('userId'), new FieldSorting('id'));
        $memberships = $this->positions->search($criteria, $context->context())->getEntities();

        $byPosition = [];
        foreach ($memberships as $membership) {
            $this->organizations->assertEligible($membership, $node->id, $this->getAssigneeType());
            $byPosition[$membership->getPositionId()][] = $membership->getUserId();
        }

        $userIds = [];
        foreach ($positionIds as $positionId) {
            if (!isset($byPosition[$positionId])) {
                throw WorkflowExecutionException::noAssignee($node->id, $this->getAssigneeType());
            }
            array_push($userIds, ...$byPosition[$positionId]);
        }

        return array_values(array_unique($userIds));
    }
}
