<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

/**
 * @internal
 */
#[Package('framework')]
class MissingApprovalAssigneeExecutionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private WorkflowService $workflows;

    private Context $context;

    private string $userId;

    protected function setUp(): void
    {
        $this->userId = TestUser::createNewAdminTestUser(static::getContainer()->get(Connection::class))->getUserId();
        $this->context = new Context(new AdminApiSource($this->userId));
        $this->workflows = static::getContainer()->get(WorkflowService::class);
    }

    #[DataProvider('automaticActions')]
    public function testAutomaticDecisionIsPersistedWithoutInventingAnApprovalTask(string $action, string $result): void
    {
        $id = $this->definition(['action' => $action]);
        $instanceId = $this->workflows->start($id, [], 'Missing supervisor', $this->context);
        $instance = $this->workflows->loadInstance($instanceId, $this->context);
        $executions = static::getContainer()->get('workflow_node_execution.repository')->search((new Criteria())->addFilter(
            new EqualsFilter('instanceId', $instanceId),
            new EqualsFilter('nodeId', 'review'),
        ), $this->context)->getEntities();

        static::assertSame($result, $instance->state());
        $execution = $executions->first();
        static::assertInstanceOf(WorkflowNodeExecutionEntity::class, $execution);
        static::assertEquals(['decision' => $action, 'reason' => 'missing-assignee'], $execution->output);
        static::assertSame(0, static::getContainer()->get('workflow_task.repository')->searchIds((new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId)), $this->context)->getTotal());
    }

    public static function automaticActions(): iterable
    {
        yield 'explicit approval' => ['approve', 'agreed'];
        yield 'explicit rejection' => ['reject', 'rejected'];
    }

    public function testDefaultBlockRollsBackTheInstanceAndNodeExecutions(): void
    {
        $id = $this->definition([]);
        try {
            $this->workflows->start($id, [], 'Missing supervisor', $this->context);
            static::fail('A missing reviewer must block by default.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame(WorkflowExecutionException::NO_ASSIGNEE, $exception->getErrorCode());
        }

        static::assertSame(0, static::getContainer()->get('workflow_instance.repository')->searchIds((new Criteria())->addFilter(new EqualsFilter('definitionId', $id)), $this->context)->getTotal());
    }

    public function testReplacementUsesTheSelectedAccountAndBlocksAfterItIsDisabled(): void
    {
        $id = $this->definition(['action' => 'assign', 'userId' => $this->userId]);
        $instanceId = $this->workflows->start($id, [], 'Replacement supervisor', $this->context);
        $tasks = static::getContainer()->get('workflow_task.repository')->search((new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId)), $this->context)->getEntities();
        static::assertCount(1, $tasks);
        $task = $tasks->first();
        static::assertInstanceOf(WorkflowTaskEntity::class, $task);
        static::assertSame($this->userId, $task->assigneeId);
        static::getContainer()->get('user.repository')->update([['id' => $this->userId, 'active' => false]], Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('review', 'user'));
        $this->workflows->start($id, [], 'Disabled replacement', $this->context);
    }

    /**
     * @param array<string, mixed> $policy
     */
    private function definition(array $policy): string
    {
        $graph = ['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start', 'properties' => []],
            ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                'name' => 'Supervisor review', 'approvalStage' => true,
                'assigneeType' => 'leader', 'assigneeValue' => [], 'countersignType' => 'countersign',
                'missingAssignee' => $policy,
            ]],
            ['id' => 'end', 'type' => 'snaker:end', 'properties' => []],
        ], 'edges' => [
            ['id' => 'to-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
            ['id' => 'to-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
        ]];
        $id = $this->workflows->createDefinition(['code' => Uuid::randomHex(), 'name' => 'Missing reviewer policy', 'graphData' => $graph], $this->context);
        $this->workflows->publishDefinition($id, $this->context);

        return $id;
    }
}
