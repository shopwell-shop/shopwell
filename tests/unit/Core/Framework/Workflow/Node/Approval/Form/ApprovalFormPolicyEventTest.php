<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicyEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalFormPolicyEvent::class)]
class ApprovalFormPolicyEventTest extends TestCase
{
    public function testRestrictionsAreMonotonicAndCannotGrantFields(): void
    {
        $event = new ApprovalFormPolicyEvent(new WorkflowInstanceEntity(), 'review', 'actor', Context::createDefaultContext());
        $event->hide('note');
        $event->readOnly('note');
        $event->readOnly('secret');
        $event->readOnly('extra');
        static::assertSame([
            'note' => ['access' => 'hidden', 'required' => false],
            'secret' => ['access' => 'hidden', 'required' => false],
        ], $event->restrict([
            'note' => ['access' => 'editable', 'required' => true],
            'secret' => ['access' => 'hidden', 'required' => false],
        ]));
    }
}
