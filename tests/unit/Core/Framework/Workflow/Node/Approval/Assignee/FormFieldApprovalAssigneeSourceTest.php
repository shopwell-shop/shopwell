<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\FormFieldApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FormFieldApprovalAssigneeSource::class)]
class FormFieldApprovalAssigneeSourceTest extends TestCase
{
    public function testReferencesAFieldWithoutFreezingTheInitiatorsChoice(): void
    {
        $source = new FormFieldApprovalAssigneeSource();
        $assignment = $source->compile(['fieldKey' => 'reviewers']);

        static::assertSame('core:form-field', $source->getName());
        static::assertSame('formField', $assignment->type);
        static::assertSame(['reviewers'], $assignment->values);
        static::assertTrue($assignment->multiple);
        static::assertSame([], $assignment->knownUserIds);
    }

    public function testCannotSelectAnArbitraryPathIntoTheInstance(): void
    {
        $this->expectException(WorkflowException::class);

        (new FormFieldApprovalAssigneeSource())->compile(['fieldKey' => 'ext.users']);
    }
}
