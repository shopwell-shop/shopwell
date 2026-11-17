<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmittingEvent;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalCollection;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalEntity;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferResubmissionSubscriber;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(PaymentTransferResubmissionSubscriber::class)]
class PaymentTransferResubmissionSubscriberTest extends TestCase
{
    public function testBoundTransferInputsCannotChangeOnResubmission(): void
    {
        $approval = new PaymentTransferApprovalEntity();
        $approval->id = str_repeat('a', 32);
        $approval->setUniqueIdentifier($approval->id);
        $repository = new StaticEntityRepository([new PaymentTransferApprovalCollection([$approval])]);
        $instance = new WorkflowInstanceEntity();
        $instance->id = str_repeat('b', 32);
        $instance->formData = ['amount' => '123', 'recipient' => 'Frozen recipient'];
        $subscriber = new PaymentTransferResubmissionSubscriber($repository);

        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('immutable_approval_inputs'));
        $subscriber->validate(new ApprovalResubmittingEvent($instance, ['amount' => '456', 'recipient' => 'Frozen recipient'], Context::createDefaultContext()));
    }

    public function testUnchangedTransferInputsMayBeResubmitted(): void
    {
        $approval = new PaymentTransferApprovalEntity();
        $approval->id = str_repeat('a', 32);
        $approval->setUniqueIdentifier($approval->id);
        $repository = new StaticEntityRepository([new PaymentTransferApprovalCollection([$approval])]);
        $instance = new WorkflowInstanceEntity();
        $instance->id = str_repeat('b', 32);
        $instance->formData = ['recipient' => 'Frozen recipient', 'amount' => '123'];
        $subscriber = new PaymentTransferResubmissionSubscriber($repository);
        $subscriber->validate(new ApprovalResubmittingEvent($instance, ['amount' => '123', 'recipient' => 'Frozen recipient'], Context::createDefaultContext()));
        static::assertSame(['recipient' => 'Frozen recipient', 'amount' => '123'], $instance->form());
    }
}
