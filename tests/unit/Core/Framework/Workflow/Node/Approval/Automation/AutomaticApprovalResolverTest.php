<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Automation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\AutomaticApprovalResolver;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AutomaticApprovalResolver::class)]
class AutomaticApprovalResolverTest extends TestCase
{
    public function testInitiatorAutomationIsExplicitAndDoesNotReadHistory(): void
    {
        $repository = StaticEntityRepository::of(WorkflowTaskHistoryCollection::class);
        $instance = new WorkflowInstanceEntity();
        $instance->initiatorId = 'initiator';
        $instance->id = 'instance';
        $instance->round = 1;
        $resolver = new AutomaticApprovalResolver($repository, StaticEntityRepository::of(WorkflowNodeExecutionCollection::class));
        static::assertSame([], $resolver->resolve($instance, new GraphNode('review', 'task', 0, 0, []), ['initiator'], Context::createDefaultContext()));
        static::assertSame(['initiator' => 'initiator'], $resolver->resolve($instance, new GraphNode('review', 'task', 0, 0, [
            'approvalAutomation' => ['approveInitiator' => true],
        ]), ['initiator', 'other'], Context::createDefaultContext()));
        static::assertSame([], $repository->searchedCriteria);
    }

    public function testRequiredReviewInputKeepsTheInitiatorTaskManual(): void
    {
        $instance = new WorkflowInstanceEntity();
        $instance->id = 'instance';
        $instance->initiatorId = 'initiator';
        $instance->formData = ['note' => ''];
        $repository = StaticEntityRepository::of(WorkflowTaskHistoryCollection::class);
        $node = new GraphNode('review', 'task', 0, 0, [
            'approvalAutomation' => ['approveInitiator' => true],
            'approvalFormPermissions' => ['note' => ['access' => 'editable', 'required' => true]],
        ]);
        static::assertSame([], (new AutomaticApprovalResolver($repository, StaticEntityRepository::of(WorkflowNodeExecutionCollection::class)))->resolve($instance, $node, ['initiator'], Context::createDefaultContext()));
    }

    public function testAdjacentDeduplicationUsesOnlyTheLatestApprovedStage(): void
    {
        $history = [];
        foreach (['previous' => 'previous', 'last' => 'previous', 'old' => 'earlier'] as $user => $node) {
            $entry = new WorkflowTaskHistoryEntity();
            $entry->id = $user;
            $entry->setUniqueIdentifier($user);
            $entry->assigneeId = $user;
            $entry->nodeId = $node;
            $history[] = $entry;
        }
        $repository = StaticEntityRepository::of(WorkflowTaskHistoryCollection::class, [new WorkflowTaskHistoryCollection($history)]);
        $instance = new WorkflowInstanceEntity();
        $instance->id = 'instance';
        $instance->initiatorId = 'initiator';
        $instance->round = 2;
        $previous = new WorkflowNodeExecutionEntity();
        $previous->id = 'execution';
        $previous->setUniqueIdentifier('execution');
        $previous->nodeId = 'previous';
        $executions = StaticEntityRepository::of(WorkflowNodeExecutionCollection::class, [new WorkflowNodeExecutionCollection([$previous])]);
        $node = new GraphNode('review', 'task', 0, 0, ['approvalAutomation' => ['deduplication' => 'adjacent']]);
        static::assertSame(['previous' => 'previous-approval', 'last' => 'previous-approval'], (new AutomaticApprovalResolver($repository, $executions))->resolve(
            $instance,
            $node,
            ['old', 'previous', 'last'],
            Context::createDefaultContext(),
        ));
        static::assertEquals([new EqualsFilter('instanceId', 'instance'), new EqualsFilter('round', 2), new EqualsFilter('decision', 'agree')], $repository->searchedCriteria[0]->getFilters());
    }
}
