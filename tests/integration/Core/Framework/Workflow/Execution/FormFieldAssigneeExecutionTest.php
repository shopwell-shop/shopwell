<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmission;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;

/**
 * @internal
 */
#[Package('framework')]
class FormFieldAssigneeExecutionTest extends TestCase
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

    public function testSubmissionFreezesTheRangeAndAssignsTheSelectedReviewer(): void
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $id = $this->definition();
        $instanceId = $service->start($id, ['people' => [$this->userId]], 'Chosen reviewer', $this->context);
        $instance = $service->loadInstance($instanceId, $this->context);
        static::assertSame([$this->userId], $instance->form()['people']);
        static::assertSame([$this->userId], $instance->snapshot()['nodes'][0]['properties']['inputSchema'][0]['candidateIds']);
        $tasks = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context);
        static::assertCount(1, $tasks);
        static::assertSame($this->userId, $tasks->first()?->assigneeId);
    }

    public function testForgedCandidateIsRejectedBeforeCreatingAnInstance(): void
    {
        $id = $this->definition();
        $this->expectException(WorkflowExecutionException::class);

        static::getContainer()->get(WorkflowService::class)->start($id, ['people' => [Uuid::randomHex()]], 'Forged reviewer', $this->context);
    }

    public function testDisabledSelectedUserCannotBeSubmittedEvenWithAnAutomaticFallback(): void
    {
        $id = $this->definition();
        static::getContainer()->get('user.repository')->update([['id' => $this->userId, 'active' => false]], Context::createDefaultContext());
        $this->expectException(WorkflowExecutionException::class);

        static::getContainer()->get(WorkflowService::class)->start($id, ['people' => [$this->userId]], 'Disabled reviewer', $this->context);
    }

    public function testResubmissionKeepsThePublishedPeopleContractAndCreatesAFreshTask(): void
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $instanceId = $service->start($this->definition(), ['people' => [$this->userId]], 'Revise selection', $this->context);
        $task = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context)->first();
        static::assertNotNull($task);
        static::getContainer()->get(ApprovalWorkflowService::class)->returnToInitiator($task->id, 'Please revise', $this->context);
        static::getContainer()->get(ApprovalResubmission::class)->resubmit($instanceId, 1, [], 'Keep selection', $this->context, 0);
        $instance = $service->loadInstance($instanceId, $this->context);
        static::assertSame(2, $instance->round);
        static::assertSame([$this->userId], $instance->form()['people']);
        $pending = static::getContainer()->get(WorkflowTaskFinder::class)->forNode($instanceId, 'review', 2, $this->context);
        static::assertCount(1, $pending);
        static::assertSame($this->userId, $pending->first()?->assigneeId);
    }

    private function definition(): string
    {
        $schema = [['key' => 'people', 'label' => 'Reviewers', 'type' => 'users', 'required' => true, 'candidateIds' => [$this->userId]]];
        $graph = ['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => $schema]],
            ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                'name' => 'Chosen reviewers', 'approvalStage' => true, 'assigneeType' => 'formField',
                'approvalOperations' => ['return'], 'assigneeValue' => ['people'], 'countersignType' => 'countersign', 'missingAssignee' => ['action' => 'approve'],
            ]],
            ['id' => 'end', 'type' => 'snaker:end'],
        ], 'edges' => [
            ['id' => 'to-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
            ['id' => 'to-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
        ]];
        $service = static::getContainer()->get(WorkflowService::class);
        $id = $service->createDefinition(['code' => Uuid::randomHex(), 'name' => 'Request-selected reviewers', 'formSchema' => $schema, 'graphData' => $graph], $this->context);
        $service->publishDefinition($id, $this->context);

        return $id;
    }
}
