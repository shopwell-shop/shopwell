<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Copy;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Preview\ApprovalNodePreviewEvent;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\User\UserCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class CopyWorkflowSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<UserCollection> $users
     */
    public function __construct(private EntityRepository $users)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ApprovalWorkflowValidationEvent::class => 'validate',
            ApprovalNodePreviewEvent::class => ['preview', -100],
        ];
    }

    public function validate(ApprovalWorkflowValidationEvent $event): void
    {
        $recipientIds = [];

        foreach ($event->graph->nodeList() as $node) {
            if ($node->type === WorkflowNodeTypes::COPY) {
                $recipientIds = [...$recipientIds, ...$this->recipients($node)];
            }
        }

        $recipientIds = array_values(array_unique($recipientIds));

        if ($recipientIds === []) {
            return;
        }

        $criteria = new Criteria($recipientIds);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('aclRoles');
        $users = $this->users->search($criteria, $event->context)->getEntities();

        if (array_diff($recipientIds, $users->getIds()) !== []) {
            throw WorkflowException::publicationRejected('copy_recipient_unavailable');
        }

        foreach ($users as $user) {
            if ($user->isAdmin()) {
                continue;
            }

            $canAccess = false;

            foreach ($user->getAclRoles() ?? [] as $role) {
                if (\in_array('workflow_instance:access', $role->getPrivileges(), true)) {
                    $canAccess = true;
                    break;
                }
            }

            if (!$canAccess) {
                throw WorkflowException::publicationRejected('copy_recipient_forbidden');
            }
        }
    }

    public function preview(ApprovalNodePreviewEvent $event): void
    {
        if ($event->node->type !== WorkflowNodeTypes::COPY || $event->getPreview() !== null) {
            return;
        }

        $event->setPreview('copy', $this->recipients($event->node));
    }

    /**
     * @return non-empty-list<string>
     */
    private function recipients(GraphNode $node): array
    {
        $recipients = $node->property('userIdList');

        if (!\is_array($recipients) || !array_is_list($recipients) || $recipients === []) {
            throw WorkflowException::publicationRejected('invalid_copy_recipients');
        }

        foreach ($recipients as $recipient) {
            if (!\is_string($recipient) || !Uuid::isValid($recipient)) {
                throw WorkflowException::publicationRejected('invalid_copy_recipients');
            }
        }

        return $recipients;
    }
}
