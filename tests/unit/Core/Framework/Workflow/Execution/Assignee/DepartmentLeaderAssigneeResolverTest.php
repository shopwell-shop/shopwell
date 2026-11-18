<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\DepartmentLeaderAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeContext;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeLookup;
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
#[CoversClass(DepartmentLeaderAssigneeResolver::class)]
class DepartmentLeaderAssigneeResolverTest extends TestCase
{
    public function testTraversesParentDepartmentsAndKeepsLeaderOrder(): void
    {
        $departmentId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $unit = $this->department($departmentId, $parentId);
        $parent = $this->department($parentId, null);
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $lookup->method('department')->willReturnCallback(static fn (string $id) => $id === $departmentId ? $unit : $parent);
        $lookup->method('departmentLeaders')->willReturnCallback(fn (string $id) => [
            $this->membership($id === $departmentId ? 'manager' : 'director', $id, 'organization'),
        ]);
        $lookup->expects($this->exactly(2))->method('assertEligible');

        static::assertSame(['manager', 'director'], $this->resolve($lookup, $departmentId, ['level' => 2, 'continuous' => true]));
    }

    public function testMissingAncestorDoesNotApproveOnlyTheLowerDepartments(): void
    {
        $departmentId = Uuid::randomHex();
        $lookup = static::createStub(OrganizationAssigneeLookup::class);
        $lookup->method('department')->willReturn($this->department($departmentId, null));
        $lookup->method('departmentLeaders')->willReturn([$this->membership('manager', $departmentId, 'organization')]);

        static::assertSame([], $this->resolve($lookup, $departmentId, ['level' => 2, 'continuous' => true]));
    }

    public function testDepartmentCycleIsRejected(): void
    {
        $departmentId = Uuid::randomHex();
        $lookup = static::createStub(OrganizationAssigneeLookup::class);
        $lookup->method('department')->willReturn($this->department($departmentId, $departmentId));

        $this->expectExceptionObject(WorkflowExecutionException::invalidGraph('The department hierarchy contains a cycle.'));
        $this->resolve($lookup, $departmentId, ['level' => 2]);
    }

    private function department(string $id, ?string $parentId): OrganizationUnitEntity
    {
        $organization = new OrganizationEntity();
        $organization->setActive(true);
        $unit = new OrganizationUnitEntity();
        $unit->setId($id);
        $unit->setParentId($parentId);
        $unit->setOrganizationId('organization');
        $unit->setActive(true);
        $unit->setOrganization($organization);

        return $unit;
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

        return (new DepartmentLeaderAssigneeResolver($lookup, new OrganizationAssigneeContext($lookup)))->resolve($instance, $node, $context);
    }

    private function membership(string $userId, string $departmentId, string $organizationId): UserOrganizationUnitEntity
    {
        $unit = new OrganizationUnitEntity();
        $unit->setId($departmentId);
        $unit->setOrganizationId($organizationId);
        $membership = new UserOrganizationUnitEntity();
        $membership->setId(Uuid::randomHex());
        $membership->setUserId($userId);
        $membership->setOrganizationUnitId($departmentId);
        $membership->setOrganizationUnit($unit);

        return $membership;
    }
}
