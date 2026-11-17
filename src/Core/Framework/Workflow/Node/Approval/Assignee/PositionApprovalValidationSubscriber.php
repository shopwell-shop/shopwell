<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\Position\PositionCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class PositionApprovalValidationSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<PositionCollection> $positions
     */
    public function __construct(private EntityRepository $positions)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ApprovalWorkflowValidationEvent::class => 'validate'];
    }

    public function validate(ApprovalWorkflowValidationEvent $event): void
    {
        $ids = [];
        foreach ($event->graph->nodeList() as $node) {
            if ($node->type === WorkflowNodeTypes::TASK && $node->property('assigneeType') === WorkflowAssigneeTypes::POSITION) {
                $values = $node->property('assigneeValue');
                if (!\is_array($values) || !array_is_list($values) || $values === []) {
                    throw WorkflowException::publicationRejected('position_unavailable');
                }
                foreach ($values as $value) {
                    if (!\is_string($value) || !Uuid::isValid($value)) {
                        throw WorkflowException::publicationRejected('position_unavailable');
                    }
                    $ids[] = $value;
                }
            }
        }

        if ($ids === []) {
            return;
        }

        $ids = array_values(array_unique($ids));
        $criteria = (new Criteria($ids))->addFilter(new EqualsFilter('active', true));
        $available = $this->positions->searchIds($criteria, $event->context)->getIds();
        if (array_diff($ids, $available) !== []) {
            throw WorkflowException::publicationRejected('position_unavailable');
        }
    }
}
