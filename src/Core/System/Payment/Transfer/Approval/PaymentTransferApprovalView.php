<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestCollection;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalView
{
    /**
     * @param EntityRepository<ApprovalRequestCollection> $requests
     * @param EntityRepository<PaymentTransferApprovalResultCollection> $results
     */
    public function __construct(
        private readonly EntityRepository $requests,
        private readonly EntityRepository $results,
        private readonly PaymentTransferApprovalRequestStore $evidence,
    ) {
    }

    /**
     * @param list<string> $transferIds
     *
     * @return array<string, array<string, mixed>>
     */
    public function summaries(array $transferIds, Context $context): array
    {
        if ($transferIds === []) {
            return [];
        }

        $requests = $this->requests->search($this->criteria($transferIds), $context)->getEntities();
        $results = $this->results->search(new Criteria($transferIds), $context)->getEntities();
        $summaries = array_fill_keys($transferIds, [
            'status' => 'not_started',
            'currentAssignees' => [],
            'detailRoute' => null,
        ]);
        foreach ($requests as $request) {
            $summaries[$request->getBusinessId()] = $this->summary($request, $results->get($request->getBusinessId()), $context);
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(PaymentTransferEntity $transfer, Context $context): array
    {
        $transferId = $transfer->id;
        $criteria = $this->criteria([$transferId])->addAssociation('instance.release.definition');
        $request = $this->requests->search($criteria, $context)->first();
        if ($request === null) {
            return [
                'status' => 'not_started',
                'releaseId' => null,
                'releaseName' => null,
                'revision' => null,
                'instanceId' => null,
                'detailRoute' => null,
                'canCancel' => false,
                'canReapply' => false,
                'previousTransfer' => null,
                'recipientLabel' => null,
                'paymentAccountLabel' => null,
            ];
        }

        $result = $this->results->search(new Criteria([$transferId]), $context)->first();
        $instance = $request->getInstance();
        $release = $instance?->getRelease();
        $source = $context->getSource();
        $canAct = $source instanceof AdminApiSource
            && $source->getIntegrationId() === null
            && $source->getUserId() === $request->getInitiatorId()
            && $context->isAllowed('payment_transfer:create')
            && $transfer->submitAttempts === 0;
        $state = $transfer->statusMachineState?->getTechnicalName();

        return $this->summary($request, $result, $context) + [
            'instanceId' => $request->getInstanceId(),
            'releaseId' => $release?->getId(),
            'releaseName' => $release?->getDefinition()?->getName(),
            'revision' => $release?->getDraftRevision(),
            'canCancel' => $canAct && $state === PaymentTransferStates::STATE_CREATED && $instance?->getStatus() === ApprovalInstanceStatus::Running,
            'canReapply' => $canAct && (
                ($state === PaymentTransferStates::STATE_CREATED && $result?->outcome === ApprovalOutcome::Rejected)
                || ($state === PaymentTransferStates::STATE_CANCELED && $instance?->getStatus() === ApprovalInstanceStatus::Withdrawn)
            ),
            'previousTransfer' => $this->evidence->previousTransfer($transferId, $context),
            'recipientLabel' => null,
            'paymentAccountLabel' => null,
        ];
    }

    /**
     * @param list<string> $transferIds
     */
    private function criteria(array $transferIds): Criteria
    {
        return (new Criteria())->addAssociation('instance')->addFilter(
            new EqualsFilter('businessType', PaymentTransferApprovalRequestService::BUSINESS_TYPE),
            new EqualsAnyFilter('businessId', $transferIds),
            new EqualsFilter('submissionRound', 1),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ApprovalRequestEntity $request, ?PaymentTransferApprovalResultEntity $result, Context $context): array
    {
        $instance = $request->getInstance();
        $status = $result?->outcome->value ?? match ($instance?->getStatus()) {
            ApprovalInstanceStatus::Terminated, ApprovalInstanceStatus::Withdrawn => 'canceled',
            null => 'unavailable',
            default => 'pending',
        };
        $source = $context->getSource();
        $canOpen = $source instanceof AdminApiSource
            && $source->getUserId() === $request->getInitiatorId()
            && $context->isAllowed('approval_flow:access');

        return [
            'status' => $status,
            'currentAssignees' => [],
            'detailRoute' => $canOpen ? ['name' => 'sw.approval.flow.detail', 'params' => ['id' => $request->getId()]] : null,
        ];
    }
}
