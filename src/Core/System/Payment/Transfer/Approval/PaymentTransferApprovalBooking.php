<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;

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
}
