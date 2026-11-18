<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class RoleApprovalValidationSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<AclRoleCollection> $roles
     */
    public function __construct(private EntityRepository $roles)
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
            if ($node->type === WorkflowNodeTypes::TASK && $node->property('assigneeType') === WorkflowAssigneeTypes::ROLE) {
                $values = $node->property('assigneeValue');
                if (!\is_array($values) || !array_is_list($values) || $values === []) {
                    throw WorkflowException::publicationRejected('role_unavailable');
                }
                foreach ($values as $value) {
                    if (!\is_string($value) || !Uuid::isValid($value)) {
                        throw WorkflowException::publicationRejected('role_unavailable');
                    }
                    $ids[] = $value;
                }
            }
        }

        if ($ids === []) {
            return;
        }

        $ids = array_values(array_unique($ids));
        $criteria = new Criteria($ids);
        $available = $this->roles->searchIds($criteria, $event->context)->getIds();
        if (array_diff($ids, $available) !== []) {
            throw WorkflowException::publicationRejected('role_unavailable');
        }
    }
}
