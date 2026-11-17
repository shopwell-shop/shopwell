<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalOperationRegistrationEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActions;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActionsEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskPolicy;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalTaskPolicy::class)]
#[CoversClass(ApprovalOperationRegistrationEvent::class)]
#[CoversClass(ApprovalTaskActionsEvent::class)]
class ApprovalTaskPolicyTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private ApprovalTaskPolicy $policy;

    private WorkflowTaskEntity $task;

    private WorkflowInstanceEntity $instance;

    private Context $context;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->policy = new ApprovalTaskPolicy($this->dispatcher);
        $this->task = new WorkflowTaskEntity();
        $this->task->assigneeId = '0123456789abcdef0123456789abcdef';
        $this->task->nodeId = 'review';
        $this->task->stateMachineState = new StateMachineStateEntity();
        $this->task->stateMachineState->setTechnicalName('todo');
        $this->instance = new WorkflowInstanceEntity();
        $this->instance->currentNodeId = 'review';
        $this->instance->stateMachineState = new StateMachineStateEntity();
        $this->instance->stateMachineState->setTechnicalName('running');
        $this->instance->graphSnapshot = ['nodes' => [['id' => 'review', 'type' => 'snaker:task']], 'edges' => []];
        $source = new AdminApiSource($this->task->assigneeId);
        $source->setPermissions(['workflow_task:decide']);
        $this->context = new Context($source);
    }

    public function testAbsentAndEmptyHaveDifferentMeanings(): void
    {
        static::assertSame(ApprovalTaskActions::OPERATIONS, $this->policy->available($this->task, $this->instance, $this->context));
        $this->instance->graphSnapshot['nodes'][0]['properties']['approvalOperations'] = [];
        static::assertSame([], $this->policy->available($this->task, $this->instance, $this->context));
        static::assertSame([], $this->policy->normalize([]));
    }

    public function testPluginDeclaresAnOperationAndRestrictsItsEligibility(): void
    {
        $this->dispatcher->addListener(ApprovalOperationRegistrationEvent::class, static function (ApprovalOperationRegistrationEvent $event): void {
            $event->add('example:consult');
        });
        static::assertSame(['example:consult'], $this->policy->normalize(['example:consult']));
        $this->instance->graphSnapshot['nodes'][0]['properties']['approvalOperations'] = ['example:consult', ApprovalTaskActions::RETURN];
        $this->dispatcher->addListener(ApprovalTaskActionsEvent::class, static function (ApprovalTaskActionsEvent $event): void {
            $event->deny('example:consult');
        });
        static::assertSame([ApprovalTaskActions::RETURN], $this->policy->available($this->task, $this->instance, $this->context));
        $this->expectExceptionObject(WorkflowExecutionException::forbidden('This operation is not available for the current approval task.'));
        $this->policy->assertAllowed('example:consult', $this->task, $this->instance, $this->context);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['return'];
        yield 'object' => [['return' => true]];
        yield 'unknown' => [['example:uninstalled']];
        yield 'duplicate' => [['return', 'return']];
        yield 'non-string' => [[true]];
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedOrUnavailableConfiguration(mixed $configuration): void
    {
        $this->expectException(WorkflowExecutionException::class);
        $this->policy->normalize($configuration);
    }

    public function testAnUninstalledPluginDoesNotEnableOrHideOtherOperations(): void
    {
        $this->instance->graphSnapshot['nodes'][0]['properties']['approvalOperations'] = ['example:consult', ApprovalTaskActions::RETURN];
        static::assertSame([ApprovalTaskActions::RETURN], $this->policy->available($this->task, $this->instance, $this->context));
    }

    public function testMalformedSnapshotOperationsNeverFallBackToEnabledDefaults(): void
    {
        $this->instance->graphSnapshot['nodes'][0]['properties']['approvalOperations'] = null;
        static::assertSame([], $this->policy->available($this->task, $this->instance, $this->context));
    }

    public function testStaleRoundsAndDifferentActorsHaveNoOperations(): void
    {
        $this->instance->round = 2;
        static::assertSame([], $this->policy->available($this->task, $this->instance, $this->context));
        $this->instance->round = 1;
        $this->task->assigneeId = 'ffffffffffffffffffffffffffffffff';
        static::assertSame([], $this->policy->available($this->task, $this->instance, $this->context));
    }
}
