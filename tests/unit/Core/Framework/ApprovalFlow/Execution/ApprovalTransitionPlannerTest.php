<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionGraph;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionState;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalTransitionPlanner;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\OpenScopeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\WaitEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ActivateRoutes;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CloseScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteExecution;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CreateApprovalTasks;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\MarkExecutionAdvanced;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\OpenScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\RejectInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\WaitForArrival;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalTransitionPlanner::class)]
class ApprovalTransitionPlannerTest extends TestCase
{
    public function testAnArrivalProducesOneOrderedPlanForScopeClosureAndContinuation(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'scope');

        $plan = (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new AdvanceEffect());

        static::assertSame($state, $plan->execution);
        static::assertCount(3, $plan->steps);
        static::assertEquals(new CloseScope('scope'), $plan->steps[0]);
        static::assertInstanceOf(CompleteExecution::class, $plan->steps[1]);
        static::assertEquals(new ActivateRoutes([['connectionId' => 'review-end', 'nextNodeId' => 'end']]), $plan->steps[2]);
        static::assertSame(ApprovalNodeExecutionStatus::Waiting, $state->status);
        static::assertFalse($state->advanced);
    }

    public function testAnApprovedGroupAdvancesWithoutReenteringItsNodeRule(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Completed, outcome: ApprovalOutcome::Approved, approvalPolicy: 'core:all');

        $plan = (new ApprovalTransitionPlanner())->advance($this->graph(), $state);

        static::assertInstanceOf(MarkExecutionAdvanced::class, $plan->steps[0]);
        static::assertInstanceOf(ActivateRoutes::class, $plan->steps[1]);
    }

    public function testACommittedRejectionCancelsInsteadOfExpandingAnyApprovedRoute(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Completed, outcome: ApprovalOutcome::Approved, approvalPolicy: 'core:all');

        $plan = (new ApprovalTransitionPlanner())->reject($state);

        static::assertCount(1, $plan->steps);
        static::assertInstanceOf(RejectInstance::class, $plan->steps[0]);
    }

    public function testOnlyThePublishedEndMayProduceACompletionPlan(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidGraphPlan('Only the published end boundary may complete the process.'));

        (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new CompleteEffect(ApprovalOutcome::Approved));
    }

    public function testThePublishedEndRetainsTheFrozenVerdict(): void
    {
        $state = new ApprovalExecutionState('execution', 'end', ApprovalNodeExecutionStatus::Waiting);

        $plan = (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new CompleteEffect(ApprovalOutcome::Rejected));

        static::assertEquals(new CompleteInstance(ApprovalOutcome::Rejected), $plan->steps[0]);
    }

    public function testWaitingRequiresTheCurrentArrivalSubscription(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'scope');

        $plan = (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new WaitEffect());

        static::assertCount(1, $plan->steps);
        static::assertInstanceOf(WaitForArrival::class, $plan->steps[0]);
    }

    public function testWaitingCannotInventAnExternalSubscription(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('review', 'Waiting requires a runtime-owned scope arrival subscription.'));

        (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new WaitEffect());
    }

    public function testApprovalPlanningUsesAlreadyResolvedMembersAndTheFrozenPolicy(): void
    {
        $member = 'reviewer';
        $intent = new CreateApprovalTasks([&$member], 'example:quorum', '1.2.0');
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);

        $plan = (new ApprovalTransitionPlanner())->enter($this->graph(), $state, $intent);
        $member = 'changed';

        static::assertSame([$intent], $plan->steps);
        static::assertSame(['reviewer'], $intent->assigneeIds);
        static::assertSame('example:quorum', $intent->policy);
        static::assertSame('1.2.0', $intent->policyVersion);
    }

    public function testAJoinCannotReplaceItsArrivalSubscriptionWithApprovalTasks(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'scope');
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('review', 'A return boundary must wait or advance.'));

        (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new CreateApprovalTasks(['reviewer'], 'core:all', '1.0.0'));
    }

    public function testAConditionalSelectionPlansOnlyTheChosenPublishedRoute(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $graph->connections[1]->sourcePort = 'low';
        $higher = clone $graph->connections[1];
        $higher->id = 'review-escalated';
        $higher->sourcePort = 'high';
        $higher->target = 'escalated';
        $graph->connections[] = $higher;
        $node = clone $graph->nodes[1];
        $node->id = 'escalated';
        $graph->nodes[] = $node;
        $edge = clone $graph->connections[1];
        $edge->id = 'escalated-end';
        $edge->source = 'escalated';
        $edge->sourcePort = 'out';
        $graph->connections[] = $edge;
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting);

        $plan = (new ApprovalTransitionPlanner())->enter($index, $state, new AdvanceEffect(['high']));

        static::assertEquals(new ActivateRoutes([['connectionId' => 'review-escalated', 'nextNodeId' => 'escalated']]), $plan->steps[1]);
    }

    public function testAnExplicitScopePlansItsActualPublishedBranches(): void
    {
        $state = new ApprovalExecutionState('execution', 'start', ApprovalNodeExecutionStatus::Waiting);

        $plan = (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new OpenScopeEffect(['out'], 'end'));

        static::assertEquals(new OpenScope([['connectionId' => 'start-review', 'nextNodeId' => 'review']], 'end'), $plan->steps[0]);
    }

    public function testAReturnBoundaryCannotOpenAnotherScopeBeforeClosingItsOwn(): void
    {
        $state = new ApprovalExecutionState('execution', 'review', ApprovalNodeExecutionStatus::Waiting, waitingScopeId: 'current');
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('review', 'A return boundary must close its scope before opening another.'));

        (new ApprovalTransitionPlanner())->enter($this->graph(), $state, new OpenScopeEffect(['out'], 'end'));
    }

    public function testMultipleSelectedRoutesRequireAnExplicitScope(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $other = clone $graph->connections[0];
        $other->id = 'other';
        $other->sourcePort = 'other';
        $graph->connections[] = $other;
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $state = new ApprovalExecutionState('execution', 'start', ApprovalNodeExecutionStatus::Waiting);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('start', 'Multiple activations require an explicit child scope.'));

        (new ApprovalTransitionPlanner())->enter($index, $state, new AdvanceEffect(['out', 'other']));
    }

    private function graph(): ApprovalExecutionGraph
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);

        return new ApprovalExecutionGraph('release', 'start', 'end', $graph);
    }
}
