<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final class FormFieldApprovalValidationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ApprovalWorkflowValidationEvent::class => 'validate'];
    }

    public function validate(ApprovalWorkflowValidationEvent $event): void
    {
        $fields = $event->graph->singleStartNode()->property('inputSchema', []);
        $people = [];
        foreach ($fields as $field) {
            if (\in_array($field['type'], ['user', 'users'], true)) {
                $people[] = $field['key'];
            }
        }

        foreach ($event->graph->nodeList() as $node) {
            if ($node->type !== WorkflowNodeTypes::TASK || $node->property('assigneeType') !== WorkflowAssigneeTypes::FORM_FIELD) {
                continue;
            }

            $reference = $node->property('assigneeValue');
            if (!\is_array($reference) || !array_is_list($reference) || \count($reference) !== 1 || !\in_array($reference[0], $people, true)) {
                throw WorkflowException::publicationRejected('approval_invalid_form_assignee');
            }
        }
    }
}
