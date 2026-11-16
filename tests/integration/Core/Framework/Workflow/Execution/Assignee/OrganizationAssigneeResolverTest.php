<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\Assignee;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\Organization\OrganizationDefaults;

/**
 * @internal
 */
#[Package('framework')]
class OrganizationAssigneeResolverTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testDepartmentLeadersKeepTheirConfiguredOrder(): void
    {
        $initiator = $this->createUser();
        $first = $this->createUser();
        $second = $this->createUser();
        static::getContainer()->get('user_organization_unit.repository')->update([
            ['id' => $this->membershipId($second), 'isLeader' => true, 'leaderPosition' => 20],
            ['id' => $this->membershipId($first), 'isLeader' => true, 'leaderPosition' => 10],
        ], Context::createDefaultContext());

        static::assertSame([$first, $second], $this->resolve($initiator, WorkflowAssigneeTypes::UNIT_LEADER));
    }

    public function testDisabledLeaderDoesNotSilentlyReduceTheApprovalQuorum(): void
    {
        $initiator = $this->createUser();
        $leader = $this->createUser();
        static::getContainer()->get('user_organization_unit.repository')->update([[
            'id' => $this->membershipId($leader), 'isLeader' => true,
        ]], Context::createDefaultContext());
        static::getContainer()->get('user.repository')->update([['id' => $leader, 'active' => false]], Context::createDefaultContext());

        $this->expectException(WorkflowExecutionException::class);
        $this->resolve($initiator, WorkflowAssigneeTypes::UNIT_LEADER);
    }

    public function testLeaderWithoutDecisionPermissionIsRejected(): void
    {
        $initiator = $this->createUser();
        $leader = $this->createUser();
        static::getContainer()->get('user_organization_unit.repository')->update([[
            'id' => $this->membershipId($leader), 'isLeader' => true,
        ]], Context::createDefaultContext());
        static::getContainer()->get('user.repository')->update([['id' => $leader, 'admin' => false]], Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('approval', WorkflowAssigneeTypes::UNIT_LEADER));
        $this->resolve($initiator, WorkflowAssigneeTypes::UNIT_LEADER);
    }

    public function testInactivePrimaryOrganizationDoesNotResolveLeaders(): void
    {
        $initiator = $this->createUser();
        static::getContainer()->get('user_organization_unit.repository')->update([[
            'id' => $this->membershipId($initiator), 'isLeader' => true,
        ]], Context::createDefaultContext());
        static::getContainer()->get('organization.repository')->update([[
            'id' => OrganizationDefaults::ORGANIZATION, 'active' => false,
        ]], Context::createDefaultContext());

        static::assertSame([], $this->resolve($initiator, WorkflowAssigneeTypes::UNIT_LEADER));
    }

    /**
     * @return list<string>
     */
    private function resolve(string $initiatorId, string $type): array
    {
        $instance = new WorkflowInstanceEntity();
        $instance->initiatorId = $initiatorId;
        $node = new GraphNode('approval', 'task', 0, 0, ['assigneeType' => $type]);
        $context = new WorkflowExecutionContext(GraphData::fromArray(['nodes' => [], 'edges' => []]), $instance, Context::createDefaultContext(), $initiatorId, 1);

        return static::getContainer()->get(AssigneeResolverRegistry::class)->for($type)->resolve($instance, $node, $context);
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        $localeId = static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM locale LIMIT 1');
        static::getContainer()->get('user.repository')->create([[
            'id' => $id, 'username' => $id, 'name' => 'Organization approver', 'email' => $id . '@example.com',
            'password' => 'shopwell', 'active' => true, 'admin' => true, 'localeId' => Uuid::fromBytesToHex($localeId),
        ]], Context::createDefaultContext());

        return $id;
    }

    private function membershipId(string $userId): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId), new EqualsFilter('organizationUnitId', OrganizationDefaults::UNIT));

        $id = static::getContainer()->get('user_organization_unit.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }
}
