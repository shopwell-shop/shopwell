<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitCollection;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitEntity;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionEntity;

/**
 * @internal
 */
#[Package('framework')]
class OrganizationAssigneeLookup
{
    /**
     * @param EntityRepository<UserOrganizationUnitCollection> $memberships
     * @param EntityRepository<OrganizationUnitCollection> $units
     */
    public function __construct(
        private readonly EntityRepository $memberships,
        private readonly EntityRepository $units,
    ) {
    }

    public function primaryMembership(string $userId, Context $context): ?UserOrganizationUnitEntity
    {
        $criteria = $this->criteria();
        $criteria->addFilter(
            new EqualsFilter('userId', $userId),
            new EqualsFilter('isPrimary', true),
            new EqualsFilter('organizationUnit.active', true),
            new EqualsFilter('organizationUnit.organization.active', true),
        );
        $criteria->setLimit(1);

        return $this->memberships->search($criteria, $context)->first();
    }

    /**
     * @return list<UserOrganizationUnitEntity>
     */
    public function departmentLeaders(string $unitId, Context $context): array
    {
        $criteria = $this->criteria();
        $criteria->addFilter(new EqualsFilter('organizationUnitId', $unitId), new EqualsFilter('isLeader', true));
        $criteria->addSorting(new FieldSorting('leaderPosition'), new FieldSorting('id'));

        return array_values($this->memberships->search($criteria, $context)->getElements());
    }

    public function membershipForDepartment(string $userId, string $unitId, Context $context): ?UserOrganizationUnitEntity
    {
        $criteria = $this->criteria()->addFilter(new EqualsFilter('userId', $userId), new EqualsFilter('organizationUnitId', $unitId));
        $criteria->setLimit(1);

        return $this->memberships->search($criteria, $context)->first();
    }

    public function membership(string $id, Context $context): ?UserOrganizationUnitEntity
    {
        $criteria = $this->criteria()->setIds([$id]);

        return $this->memberships->search($criteria, $context)->first();
    }

    public function department(string $id, Context $context): ?OrganizationUnitEntity
    {
        return $this->units->search((new Criteria([$id]))->addAssociation('organization'), $context)->first();
    }

    public function assertEligible(UserOrganizationUnitEntity|UserPositionEntity $membership, string $nodeId, string $type): void
    {
        $user = $membership->getUser();
        $unit = $membership->getOrganizationUnit();
        if ($user === null || !$user->getActive() || $unit === null || !$unit->isActive() || !$unit->getOrganization()?->isActive()) {
            throw WorkflowExecutionException::noAssignee($nodeId, $type);
        }
        if ($membership instanceof UserPositionEntity && !$membership->getPosition()?->isActive()) {
            throw WorkflowExecutionException::noAssignee($nodeId, $type);
        }
        if (!ApprovalUserEligibility::isEligible($user)) {
            throw WorkflowExecutionException::noAssignee($nodeId, $type);
        }
    }

    private function criteria(): Criteria
    {
        return (new Criteria())->addAssociation('user.aclRoles')->addAssociation('organizationUnit.organization');
    }
}
