<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * Selects the approval path for new Administration transfer bookings.
 *
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalBooking
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ApprovalBindingStore $bindings,
        private readonly PaymentTransferBooking $booking,
        private readonly PaymentTransferApprovalStore $legacyApprovals,
        private readonly PaymentTransferApprovalRequestStore $requestStore,
        private readonly PaymentTransferApprovalService $legacy,
        private readonly PaymentTransferApprovalRequestService $requests,
        private readonly PaymentTransferService $transfers,
    ) {
    }

    public function usesApprovalFlow(Context $context): bool
    {
        return $this->bindings->findHead(PaymentTransferApprovalRequestService::BUSINESS_TYPE, $context)?->getActiveRevision() !== null;
    }

    public function book(PaymentTransferDraft $draft, Context $context): PaymentTransferEntity
    {
        if (!$this->usesApprovalFlow($context)) {
            return $this->legacy->book($draft, $context);
        }

        return RetryableTransaction::transactional($this->connection, function () use ($draft, $context): PaymentTransferEntity {
            $transfer = $this->booking->book($draft);
            $this->requestStore->lockTransfer($transfer->id);
            // A retried booking keeps its existing legacy approval after cutover.
            if ($this->legacyApprovals->get($transfer->id) === null) {
                // The payment route authorizes the booking. Approval's guarded writes retain the original user.
                $context->scope(Context::SYSTEM_SCOPE, fn (Context $writeContext) => $this->requests->submit($transfer->id, $writeContext));
            }

            return $transfer;
        });
    }

    /**
     * @return array{transfer: PaymentTransferEntity, replayed: bool}
     */
    public function reapply(string $transferId, PaymentTransferDraft $draft, Context $context): array
    {
        return RetryableTransaction::transactional($this->connection, function () use ($transferId, $draft, $context): array {
            $this->requestStore->lockTransfer($transferId);
            $approval = $this->requestStore->getApproval($transferId);
            if ($approval === null) {
                $app = $this->booking->resolveApp($draft->paymentAppId);
                $replayed = $draft->outNo !== null && $this->transfers->getByOutNo($app, $draft->outNo) !== null;

                return ['transfer' => $this->legacy->reapply($transferId, $draft, $context), 'replayed' => $replayed];
            }

            $source = $context->getSource();
            if (!$source instanceof AdminApiSource || $source->getIntegrationId() !== null || $source->getUserId() !== $approval['initiatorId']) {
                throw PaymentTransferApprovalException::blocked('reapplication_forbidden');
            }

            $previous = $this->transfers->getById($transferId);
            $state = $previous->statusMachineState?->getTechnicalName();
            $withdrawn = $state === PaymentTransferStates::STATE_CANCELED && $approval['status'] === ApprovalInstanceStatus::Withdrawn;
            $rejected = $state === PaymentTransferStates::STATE_CREATED && $approval['outcome'] === ApprovalOutcome::Rejected;
            if ($previous->submitAttempts !== 0 || (!$withdrawn && !$rejected)) {
                throw PaymentTransferApprovalException::blocked('reapplication_unavailable');
            }

            if ($draft->outNo === null) {
                throw PaymentTransferApprovalException::blocked('reapplication_out_number_required');
            }

            // Booking can replay even when a different caller won the insert. Only a new row can acquire lineage.
            $newTransferId = Uuid::randomHex();
            $transfer = $this->booking->book($draft, $newTransferId);
            if ($transfer->id !== $newTransferId) {
                $lineage = $this->requestStore->previousTransfer($transfer->id, $context);
                if (($lineage['id'] ?? null) !== $transferId) {
                    throw PaymentTransferApprovalException::blocked('reapplication_requires_new_transfer');
                }

                return ['transfer' => $transfer, 'replayed' => true];
            }

            $context->scope(Context::SYSTEM_SCOPE, fn (Context $writeContext) => $this->requests->submit($transfer->id, $writeContext, $previous));

            return ['transfer' => $transfer, 'replayed' => false];
        });
    }
}
