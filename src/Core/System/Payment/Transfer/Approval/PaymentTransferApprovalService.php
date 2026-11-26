<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalService
{
    public function __construct(
        private readonly PaymentTransferApprovalResultVerifier $results,
        private readonly PaymentTransferApprovalRequestStore $requestStore,
        private readonly ApprovalRequestService $approvalRequests,
        private readonly PaymentTransferService $transfers,
    ) {
    }

    public function assertApproved(PaymentTransferEntity $transfer): PaymentTransferRecipientEntity
    {
        return $this->results->recipient($transfer) ?? throw PaymentTransferApprovalException::blocked('not_started');
    }

    public function cancel(string $transferId, string $reason, Context $context): void
    {
        $this->requestStore->transactional($transferId, function () use ($transferId, $reason, $context): void {
            $approval = $this->requestStore->getApproval($transferId);
            if ($approval === null) {
                throw PaymentTransferApprovalException::blocked('not_started');
            }

            $transfer = $this->transfers->getById($transferId);
            $state = $transfer->statusMachineState?->getTechnicalName();
            $alreadyWithdrawn = $state === PaymentTransferStates::STATE_CANCELED && $approval['status'] === ApprovalInstanceStatus::Withdrawn;
            if ($transfer->submitAttempts !== 0 || ($state !== PaymentTransferStates::STATE_CREATED && !$alreadyWithdrawn)) {
                throw PaymentTransferApprovalException::blocked('transfer_already_started');
            }

            $this->approvalRequests->withdraw($approval['requestId'], $reason, $context);
            if (!$alreadyWithdrawn) {
                $this->transfers->cancel($transfer, $reason);
            }
        });
    }
}
