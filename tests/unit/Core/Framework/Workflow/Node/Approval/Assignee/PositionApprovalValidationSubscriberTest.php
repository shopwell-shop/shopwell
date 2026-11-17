<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\PositionApprovalValidationSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\Position\PositionCollection;
use Shopwell\Core\System\Position\PositionEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PositionApprovalValidationSubscriber::class)]
class PositionApprovalValidationSubscriberTest extends TestCase
{
    public function testPublicationChecksPositionsWithoutFreezingTheirMembers(): void
    {
        $position = new PositionEntity();
        $position->setId(Uuid::randomHex());
        $repository = StaticEntityRepository::of(PositionCollection::class, [[$position->getId()]]);
        $event = $this->event([$position->getId()]);

        (new PositionApprovalValidationSubscriber($repository))->validate($event);

        static::assertSame([$position->getId()], $repository->searchedCriteria[0]->getIds());
        static::assertEquals([new EqualsFilter('active', true)], $repository->searchedCriteria[0]->getFilters());
    }

    public function testUnavailablePositionRejectsPublicationAndRevalidation(): void
    {
        $repository = StaticEntityRepository::of(PositionCollection::class, [[]]);
        $this->expectExceptionObject(WorkflowException::publicationRejected('position_unavailable'));

        (new PositionApprovalValidationSubscriber($repository))->validate($this->event([Uuid::randomHex()]));
    }

    /**
     * @param list<string> $ids
     */
    private function event(array $ids): ApprovalWorkflowValidationEvent
    {
        $graph = GraphData::fromArray([
            'nodes' => [['id' => 'approval', 'type' => 'snaker:task', 'properties' => [
                'assigneeType' => WorkflowAssigneeTypes::POSITION, 'assigneeValue' => $ids,
            ]]], 'edges' => [],
        ]);

        return new ApprovalWorkflowValidationEvent($graph, Context::createDefaultContext());
    }
}
