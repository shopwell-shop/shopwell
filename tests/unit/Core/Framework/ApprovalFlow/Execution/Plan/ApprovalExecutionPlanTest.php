<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionState;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ActivateRoutes;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ApprovalExecutionPlan;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CloseScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteExecution;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ExecutionChange;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\WaitForArrival;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalExecutionPlan::class)]
class ApprovalExecutionPlanTest extends TestCase
{
    public function testAnUnknownChangeIsRejectedBeforeThePlanCanBeApplied(): void
    {
        $change = new class implements ExecutionChange {};
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('Unsupported execution change ' . $change::class . '.'));

        new ApprovalExecutionPlan($state, new CompleteExecution(), $change);
    }

    public function testATerminalResultCannotSilentlyDiscardAFollowingChange(): void
    {
        $state = new ApprovalExecutionState('execution', 'end', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('No change may follow an activation, wait or terminal result.'));

        new ApprovalExecutionPlan($state, new CompleteInstance(ApprovalOutcome::Approved), new WaitForArrival());
    }

    public function testRoutesCannotActivateBeforeTheCurrentExecutionIsConsumed(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('Complete or advance the current execution before activating its routes.'));

        new ApprovalExecutionPlan($state, new ActivateRoutes([['connectionId' => 'edge', 'nextNodeId' => 'end']]));
    }

    public function testAnExecutionCannotBeConsumedTwice(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('A plan may consume its current execution only once.'));

        new ApprovalExecutionPlan($state, new CompleteExecution(), new CompleteInstance(ApprovalOutcome::Approved));
    }

    public function testAnIncompletePlanCannotLeaveTheProcessWithoutAContinuation(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('A plan must finish at an activation, wait or terminal result.'));

        new ApprovalExecutionPlan($state, new CompleteExecution());
    }

    public function testAPlanCannotCloseAnotherExecutionsReturnScope(): void
    {
        $state = new ApprovalExecutionState('execution', 'join', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'current');
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('Close the current return scope before consuming its execution.'));

        new ApprovalExecutionPlan($state, new CloseScope('other'), new WaitForArrival());
    }

    public function testAPlanCannotCloseItsReturnScopeTwice(): void
    {
        $state = new ApprovalExecutionState('execution', 'join', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'current');
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('Close the current return scope before consuming its execution.'));

        new ApprovalExecutionPlan($state, new CloseScope('current'), new CloseScope('current'), new WaitForArrival());
    }

    public function testNamedRemainingStepsStillProduceAnOrderedList(): void
    {
        $state = new ApprovalExecutionState('execution', 'join', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'current');
        $plan = new ApprovalExecutionPlan($state, new CloseScope('current'), complete: new CompleteExecution(), activate: new ActivateRoutes([['connectionId' => 'edge', 'nextNodeId' => 'end']]));

        static::assertSame([0, 1, 2], array_keys($plan->steps));
        static::assertInstanceOf(CloseScope::class, $plan->steps[0]);
        static::assertInstanceOf(CompleteExecution::class, $plan->steps[1]);
        static::assertInstanceOf(ActivateRoutes::class, $plan->steps[2]);
    }

    public function testAClosedScopeCannotRetainItsOldArrivalSubscription(): void
    {
        $state = new ApprovalExecutionState('execution', 'join', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'current');
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionPlan('A closed return scope must continue through a completed execution.'));

        new ApprovalExecutionPlan($state, new CloseScope('current'), new WaitForArrival());
    }

    public function testCallerReferencesCannotChangeTheRoutesOfAPreparedPlan(): void
    {
        $target = 'end';
        $routes = [['connectionId' => 'edge', 'nextNodeId' => &$target]];
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $plan = new ApprovalExecutionPlan($state, new CompleteExecution(), new ActivateRoutes($routes));

        $target = 'other';

        static::assertInstanceOf(ActivateRoutes::class, $plan->steps[1]);
        static::assertSame([['connectionId' => 'edge', 'nextNodeId' => 'end']], $plan->steps[1]->routes);
    }
}
