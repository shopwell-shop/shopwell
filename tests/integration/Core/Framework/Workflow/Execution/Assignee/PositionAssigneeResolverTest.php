<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\Assignee;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceMetadataEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\Organization\OrganizationDefaults;

/**
 * @internal
 */
#[Package('framework')]
class PositionAssigneeResolverTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSelectedPositionOrderAndStableMemberOrderWithDeduplication(): void
    {
        $initiator = $this->createUser();
        $first = $this->createUser();
        $second = $this->createUser();
        $finance = $this->createPosition();
        $manager = $this->createPosition();
        $this->assign($second, $finance);
        $this->assign($first, $finance);
        $this->assign($second, $manager);

        $financeMembers = [$first, $second];
        sort($financeMembers);
        static::assertSame($financeMembers, $this->resolve($initiator, [$finance]));
        static::assertSame([$second, $first], $this->resolve($initiator, [$manager, $finance]));
    }

    public function testSamePositionInAnotherDepartmentIsExcluded(): void
    {
        $initiator = $this->createUser();
        $local = $this->createUser();
        $remote = $this->createUser();
        $position = $this->createPosition();
        $department = Uuid::randomHex();
        static::getContainer()->get('organization_unit.repository')->create([[
            'id' => $department, 'organizationId' => OrganizationDefaults::ORGANIZATION,
            'name' => 'Other department', 'active' => true,
        ]], Context::createDefaultContext());
        static::getContainer()->get('user_organization_unit.repository')->create([[
            'id' => Uuid::randomHex(), 'userId' => $remote, 'organizationUnitId' => $department, 'isPrimary' => false,
        ]], Context::createDefaultContext());
        $this->assign($local, $position);
        $this->assign($remote, $position, $department);

        static::assertSame([$local], $this->resolve($initiator, [$position]));
    }

    public function testUnstaffedPositionCannotSilentlyReduceTheQuorum(): void
    {
        $initiator = $this->createUser();
        $staffed = $this->createPosition();
        $empty = $this->createPosition();
        $this->assign($this->createUser(), $staffed);

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('approval', WorkflowAssigneeTypes::POSITION));
        $this->resolve($initiator, [$staffed, $empty]);
    }

    public function testInactiveMemberIsRejected(): void
    {
        $initiator = $this->createUser();
        $member = $this->createUser();
        $position = $this->createPosition();
        $this->assign($member, $position);
        static::getContainer()->get('user.repository')->update([['id' => $member, 'active' => false]], Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('approval', WorkflowAssigneeTypes::POSITION));
        $this->resolve($initiator, [$position]);
    }

    public function testMemberWithoutDecisionPermissionIsRejected(): void
    {
        $initiator = $this->createUser();
        $member = $this->createUser();
        $position = $this->createPosition();
        $this->assign($member, $position);
        static::getContainer()->get('user.repository')->update([['id' => $member, 'admin' => false]], Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('approval', WorkflowAssigneeTypes::POSITION));
        $this->resolve($initiator, [$position]);
    }

    public function testInactivePositionIsRejected(): void
    {
        $initiator = $this->createUser();
        $position = $this->createPosition();
        $this->assign($this->createUser(), $position);
        static::getContainer()->get('position.repository')->update([['id' => $position, 'active' => false]], Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('approval', WorkflowAssigneeTypes::POSITION));
        $this->resolve($initiator, [$position]);
    }

    public function testInactivePositionRejectsLiveValidationThroughRegisteredSubscriber(): void
    {
        $position = $this->createPosition();
        static::getContainer()->get('position.repository')->update([['id' => $position, 'active' => false]], Context::createDefaultContext());
        $graph = GraphData::fromArray(['nodes' => [[
            'id' => 'approval', 'type' => 'snaker:task',
            'properties' => ['assigneeType' => WorkflowAssigneeTypes::POSITION, 'assigneeValue' => [$position]],
        ]], 'edges' => []]);

        $this->expectExceptionObject(WorkflowException::publicationRejected('position_unavailable'));
        static::getContainer()->get('event_dispatcher')->dispatch(new ApprovalWorkflowValidationEvent($graph, Context::createDefaultContext()));
    }

    /**
     * @param list<string> $positions
     *
     * @return list<string>
     */
    private function resolve(string $initiator, array $positions): array
    {
        $instance = new WorkflowInstanceEntity();
        $instance->initiatorId = $initiator;
        $metadata = new WorkflowInstanceMetadataEvent($initiator, Context::createDefaultContext());
        static::getContainer()->get('event_dispatcher')->dispatch($metadata);
        $instance->ext = $metadata->getMetadata();
        $node = new GraphNode('approval', 'task', 0, 0, ['assigneeType' => WorkflowAssigneeTypes::POSITION, 'assigneeValue' => $positions]);
        $execution = new WorkflowExecutionContext(GraphData::fromArray(['nodes' => [], 'edges' => []]), $instance, Context::createDefaultContext(), $initiator, 1);

        return static::getContainer()->get(AssigneeResolverRegistry::class)->for(WorkflowAssigneeTypes::POSITION)->resolve($instance, $node, $execution);
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        $locale = static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM locale LIMIT 1');
        static::getContainer()->get('user.repository')->create([[
            'id' => $id, 'username' => $id, 'name' => 'Position approver', 'email' => $id . '@example.com',
            'password' => 'shopwell', 'active' => true, 'admin' => true, 'localeId' => Uuid::fromBytesToHex($locale),
        ]], Context::createDefaultContext());

        return $id;
    }

    private function createPosition(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('position.repository')->create([[
            'id' => $id, 'name' => 'Finance reviewer', 'active' => true,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function assign(string $user, string $position, string $department = OrganizationDefaults::UNIT): void
    {
        static::getContainer()->get('user_position.repository')->create([[
            'id' => Uuid::randomHex(), 'userId' => $user, 'positionId' => $position,
            'organizationUnitId' => $department, 'isPrimary' => false,
        ]], Context::createDefaultContext());
    }
}
