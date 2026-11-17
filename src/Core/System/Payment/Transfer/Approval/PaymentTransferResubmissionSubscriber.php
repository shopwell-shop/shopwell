<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmittingEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Booked payout inputs are immutable; additional resubmission explanations are audited separately.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentTransferResubmissionSubscriber implements EventSubscriberInterface
{
    private const INPUT_KEYS = ['amount', 'currency', 'recipient', 'paymentAccount', 'reason'];

    /**
     * @param EntityRepository<PaymentTransferApprovalCollection> $approvals
     */
    public function __construct(private EntityRepository $approvals)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ApprovalResubmittingEvent::class => 'validate', ApprovalFormPolicyEvent::class => 'restrict'];
    }

    public function validate(ApprovalResubmittingEvent $event): void
    {
        if (!$this->isBound($event->instance->id, $event->context)) {
            return;
        }

        foreach (self::INPUT_KEYS as $key) {
            if (($event->formData[$key] ?? null) !== ($event->instance->form()[$key] ?? null)) {
                throw PaymentTransferApprovalException::blocked('immutable_approval_inputs');
            }
        }
    }

    public function restrict(ApprovalFormPolicyEvent $event): void
    {
        if (!$this->isBound($event->instance->id, $event->context)) {
            return;
        }
        foreach (self::INPUT_KEYS as $key) {
            $event->readOnly($key);
        }
    }

    private function isBound(string $instanceId, Context $context): bool
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId))->setLimit(1);

        return $this->approvals->search($criteria, $context)->getEntities()->count() > 0;
    }
}
