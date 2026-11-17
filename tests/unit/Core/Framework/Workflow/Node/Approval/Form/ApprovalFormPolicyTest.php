<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalFormPolicy::class)]
class ApprovalFormPolicyTest extends TestCase
{
    public function testUnconfiguredApprovalFieldsAreReadOnly(): void
    {
        $policy = new ApprovalFormPolicy();
        static::assertSame(['reason' => ['access' => 'readonly', 'required' => false]], $policy->normalize([], $this->schema()));
    }

    public function testFreezesEditableAndRequiredPermission(): void
    {
        $permissions = ['reason' => ['access' => 'editable', 'required' => true]];
        static::assertSame($permissions, (new ApprovalFormPolicy())->normalize($permissions, $this->schema()));
    }

    public function testRejectsUnknownField(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalFormPolicy())->normalize(['secret' => ['access' => 'hidden']], $this->schema());
    }

    public function testReadOnlyFieldsCannotBeRequiredAtANode(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalFormPolicy())->normalize(['reason' => ['access' => 'readonly', 'required' => true]], $this->schema());
    }

    public function testCopyNodesCannotEditFields(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalFormPolicy())->normalize(['reason' => ['access' => 'editable']], $this->schema(), allowEdit: false);
    }

    public function testRejectsInvalidAccessAndUnknownConfiguration(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalFormPolicy())->normalize(['reason' => ['access' => 'write', 'extra' => true]], $this->schema());
    }

    public function testExplicitNullFieldPermissionIsInvalid(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalFormPolicy())->normalize(['reason' => null], $this->schema());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function schema(): array
    {
        return [['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => true]];
    }
}
