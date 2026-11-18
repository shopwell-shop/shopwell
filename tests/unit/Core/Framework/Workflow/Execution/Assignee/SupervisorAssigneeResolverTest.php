<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeContext;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeLookup;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\SupervisorAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;
use Shopwell\Core\System\Organization\OrganizationEntity;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SupervisorAssigneeResolver::class)]
class SupervisorAssigneeResolverTest extends TestCase
{
    public function testFollowsTheCapturedDepartmentAndPreservesContinuousOrder(): void
    {
        $departmentId = Uuid::randomHex();
        $initiator = $this->membership('employee', $departmentId, 'organization');
        $manager = $this->membership('manager', 'other-department', 'organization');
        $director = $this->membership('director', 'head-office', 'organization');
        $initiator->setSupervisorMembershipId($manager->getId());
        $manager->setSupervisorMembershipId($director->getId());
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $lookup->expects($this->once())->method('membershipForDepartment')->with('employee', $departmentId)->willReturn($initiator);
        $lookup->method('membership')->willReturnCallback(static fn (string $id) => $id === $manager->getId() ? $manager : $director);
        $lookup->expects($this->exactly(2))->method('assertEligible');

        static::assertSame(['manager', 'director'], $this->resolve($lookup, $departmentId, ['level' => 2, 'continuous' => true]));
    }

    public function testMissingTargetLevelDoesNotSilentlyShortenTheQuorum(): void
    {
        $departmentId = Uuid::randomHex();
        $initiator = $this->membership('employee', $departmentId, 'organization');
        $lookup = static::createStub(OrganizationAssigneeLookup::class);
        $lookup->method('membershipForDepartment')->willReturn($initiator);

        static::assertSame([], $this->resolve($lookup, $departmentId, ['level' => 2]));
    }

    public function testReportingCycleIsInvalidAndCannotTriggerAnAutomaticDecision(): void
    {
        $departmentId = Uuid::randomHex();
        $initiator = $this->membership('employee', $departmentId, 'organization');
        $initiator->setSupervisorMembershipId($initiator->getId());
        $lookup = static::createStub(OrganizationAssigneeLookup::class);
        $lookup->method('membershipForDepartment')->willReturn($initiator);

        $this->expectExceptionObject(WorkflowExecutionException::invalidGraph('The reporting hierarchy contains a cycle.'));
        $this->resolve($lookup, $departmentId, []);
    }

    public function testSupervisorCannotBelongToAnotherOrganization(): void
    {
        $departmentId = Uuid::randomHex();
        $initiator = $this->membership('employee', $departmentId, 'organization');
        $manager = $this->membership('manager', 'another-unit', 'another-organization');
        $initiator->setSupervisorMembershipId($manager->getId());
        $lookup = static::createStub(OrganizationAssigneeLookup::class);
        $lookup->method('membershipForDepartment')->willReturn($initiator);
        $lookup->method('membership')->willReturn($manager);

        $this->expectExceptionObject(WorkflowExecutionException::invalidGraph('A reporting hierarchy cannot cross organization boundaries.'));
        $this->resolve($lookup, $departmentId, []);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return list<string>
     */
    private function resolve(OrganizationAssigneeLookup $lookup, string $departmentId, array $configuration): array
    {
        $instance = new WorkflowInstanceEntity();
        $instance->initiatorId = 'employee';
        $instance->ext = ['core:organization' => ['departmentId' => $departmentId]];
        $node = new GraphNode('approval', 'task', 0, 0, ['assigneeConfig' => $configuration]);
        $context = new WorkflowExecutionContext(GraphData::fromArray(['nodes' => [], 'edges' => []]), $instance, Context::createDefaultContext(), null, 1);

        return (new SupervisorAssigneeResolver($lookup, new OrganizationAssigneeContext($lookup)))->resolve($instance, $node, $context);
    }

    private function membership(string $userId, string $departmentId, string $organizationId): UserOrganizationUnitEntity
    {
        $unit = new OrganizationUnitEntity();
        $unit->setId($departmentId);
        $unit->setOrganizationId($organizationId);
        $unit->setActive(true);
        $organization = new OrganizationEntity();
        $organization->setActive(true);
        $unit->setOrganization($organization);
        $membership = new UserOrganizationUnitEntity();
        $membership->setId(Uuid::randomHex());
        $membership->setUserId($userId);
        $membership->setOrganizationUnitId($departmentId);
        $membership->setOrganizationUnit($unit);

        return $membership;
    }
}
