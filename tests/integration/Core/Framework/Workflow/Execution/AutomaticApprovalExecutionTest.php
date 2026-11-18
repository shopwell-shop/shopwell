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
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;

/**
 * @internal
 */
#[Package('framework')]
class AutomaticApprovalExecutionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Context $context;

    private string $userId;

    protected function setUp(): void
    {
        $this->userId = TestUser::createNewAdminTestUser(static::getContainer()->get(Connection::class))->getUserId();
        $source = new AdminApiSource($this->userId);
        $source->setIsAdmin(true);
        $this->context = new Context($source);
    }

    #[DataProvider('automaticDecisions')]
    public function testExplicitNodeDecisionsDoNotInventHumanTasks(string $decision, string $state): void
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $id = $service->start($this->definition(['decision' => $decision]), [], 'Automatic review', $this->context);
        static::assertSame($state, $service->loadInstance($id, $this->context)->state());
        static::assertCount(0, static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($id, $this->context));
        $execution = static::getContainer()->get('workflow_node_execution.repository')->search((new Criteria())->addFilter(
            new EqualsFilter('instanceId', $id),
            new EqualsFilter('nodeId', 'review'),
        ), $this->context)->getEntities()->first();
        static::assertInstanceOf(WorkflowNodeExecutionEntity::class, $execution);
        static::assertEquals(['decision' => $decision, 'reason' => 'configured'], $execution->output);
    }

    public static function automaticDecisions(): iterable
    {
        yield ['approve', 'agreed'];
        yield ['reject', 'rejected'];
    }

    public function testInitiatorAutomationRetainsACompletedVoteAndItsReason(): void
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $id = $service->start($this->definition(['approveInitiator' => true]), [], 'Self review', $this->context);
        static::assertSame('agreed', $service->loadInstance($id, $this->context)->state());
        $history = static::getContainer()->get(WorkflowTaskFinder::class)->historyOf($id, $this->context);
        static::assertCount(1, $history);
        $approval = $history->first();
        static::assertInstanceOf(WorkflowTaskHistoryEntity::class, $approval);
        static::assertSame('agree', $approval->decision);
        static::assertEquals(['automatic' => true, 'reason' => 'initiator'], $approval->ext);
    }

    public function testRepeatedReviewerIsManualByDefaultAndAutomaticOnlyWhenEnabled(): void
    {
        $service = static::getContainer()->get(WorkflowService::class);
        foreach (['none', 'all', 'adjacent'] as $deduplication) {
            $id = $service->start($this->definition(['deduplication' => $deduplication], previous: true), [], 'Repeat review', $this->context);
            $first = static::getContainer()->get(WorkflowTaskFinder::class)->forNode($id, 'first', 1, $this->context)->first();
            static::assertNotNull($first);
            static::getContainer()->get(ApprovalWorkflowService::class)->approve($first->id, null, $this->context);
            static::assertSame($deduplication === 'none' ? 'running' : 'agreed', $service->loadInstance($id, $this->context)->state());
            $history = static::getContainer()->get(WorkflowTaskFinder::class)->historyOf($id, $this->context);
            static::assertCount($deduplication === 'none' ? 1 : 2, $history);
        }
    }

    #[DataProvider('mixedModes')]
    public function testAutomaticVotesPreserveTheModeAndSequentialPositions(string $mode, bool $automaticFirst): void
    {
        $other = TestUser::createNewAdminTestUser(static::getContainer()->get(Connection::class))->getUserId();
        $reviewers = $automaticFirst ? [$this->userId, $other] : [$other, $this->userId];
        $service = static::getContainer()->get(WorkflowService::class);
        $id = $service->start($this->definition(['approveInitiator' => true], reviewers: $reviewers, mode: $mode), [], 'Mixed approvals', $this->context);
        if ($mode === 'orSign') {
            static::assertSame('agreed', $service->loadInstance($id, $this->context)->state());
            $tasks = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($id, $this->context);
            static::assertSame(0, $tasks->countByState('todo'));

            return;
        }
        static::assertSame('running', $service->loadInstance($id, $this->context)->state());
        $pending = static::getContainer()->get(WorkflowTaskFinder::class)->forNode($id, 'review', 1, $this->context)->nextTodo();
        static::assertNotNull($pending);
        static::assertSame($other, $pending->assigneeId);
        $source = new AdminApiSource($other);
        $source->setIsAdmin(true);
        static::getContainer()->get(ApprovalWorkflowService::class)->approve($pending->id, null, new Context($source));
        static::assertSame('agreed', $service->loadInstance($id, $this->context)->state());
        static::assertCount(2, static::getContainer()->get(WorkflowTaskFinder::class)->historyOf($id, $this->context));
    }

    public static function mixedModes(): iterable
    {
        yield ['countersign', true];
        yield ['orSign', true];
        yield ['orderSign', true];
        yield ['orderSign', false];
    }

    /**
     * @param array<string, mixed> $automation
     * @param list<string> $reviewers
     */
    private function definition(array $automation, bool $previous = false, array $reviewers = [], string $mode = 'single'): string
    {
        $review = ['name' => 'Review', 'approvalStage' => true, 'assigneeType' => 'user', 'assigneeValue' => $reviewers === [] ? [$this->userId] : $reviewers, 'countersignType' => $mode];
        $nodes = [
            ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => []]],
            ['id' => 'review', 'type' => 'snaker:task', 'properties' => [...$review, 'approvalAutomation' => $automation]],
            ['id' => 'end', 'type' => 'snaker:end'],
        ];
        $edges = [
            ['id' => 'to-review', 'sourceNodeId' => 'start', 'targetNodeId' => $previous ? 'first' : 'review'],
            ['id' => 'to-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
        ];
        if ($previous) {
            $nodes[] = ['id' => 'first', 'type' => 'snaker:task', 'properties' => $review];
            $edges[] = ['id' => 'first-review', 'sourceNodeId' => 'first', 'targetNodeId' => 'review'];
        }
        $service = static::getContainer()->get(WorkflowService::class);
        $id = $service->createDefinition(['code' => Uuid::randomHex(), 'name' => 'Automatic approvals', 'graphData' => ['nodes' => $nodes, 'edges' => $edges]], $this->context);
        $service->publishDefinition($id, $this->context);

        return $id;
    }
}
