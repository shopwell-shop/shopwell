<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\FormFieldApprovalValidationSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FormFieldApprovalValidationSubscriber::class)]
class FormFieldApprovalValidationSubscriberTest extends TestCase
{
    public function testPeopleFieldIsValidatedAgainstThePublishedRequestSchema(): void
    {
        (new FormFieldApprovalValidationSubscriber())->validate($this->event('users'));
        $this->expectNotToPerformAssertions();
    }

    public function testTextCannotBeUsedToSubmitUnvalidatedAssigneeIdentifiers(): void
    {
        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_invalid_form_assignee'));

        (new FormFieldApprovalValidationSubscriber())->validate($this->event('text'));
    }

    private function event(string $type): ApprovalWorkflowValidationEvent
    {
        return new ApprovalWorkflowValidationEvent(GraphData::fromArray(['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => [['key' => 'people', 'label' => 'People', 'type' => $type, 'required' => true]]]],
            ['id' => 'review', 'type' => 'snaker:task', 'properties' => ['assigneeType' => 'formField', 'assigneeValue' => ['people']]],
        ], 'edges' => []]), Context::createDefaultContext());
    }
}
