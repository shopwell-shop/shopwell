<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Verifies the applied business decision against the payout's current and outgoing inputs.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalExecutionTest
 */
#[Package('framework')]
class PaymentTransferApprovalResultVerifier
{
    public function __construct(private readonly PaymentTransferApprovalRequestStore $requests)
    {
    }

    /**
     * Null means this transfer has no Approval Flow submission and belongs to the existing entry point.
     * A submitted transfer without an applied approval always fails here.
     */
    public function recipient(PaymentTransferEntity $transfer): ?PaymentTransferRecipientEntity
    {
        return $this->requests->transactional($transfer->id, function () use ($transfer): ?PaymentTransferRecipientEntity {
            $approval = $this->requests->getApproval($transfer->id);
            if ($approval === null) {
                return null;
            }
            if ($approval['outcome'] !== ApprovalOutcome::Approved) {
                throw PaymentTransferApprovalException::blocked($approval['outcome']->value ?? 'pending');
            }

            $facts = $this->requests->snapshot($transfer->id, requirePayoutEligibility: true);
            $snapshot = $facts['snapshot'];
            $revision = Hasher::hash(json_encode($snapshot, \JSON_THROW_ON_ERROR), 'sha256');
            if (!hash_equals($approval['revision'], $revision)) {
                throw PaymentTransferApprovalException::blocked('changed_transfer');
            }
            foreach (['paymentAppId', 'accountId', 'recipientId', 'outNo', 'provider', 'channel', 'amount', 'currency', 'channelParams'] as $key) {
                if ($snapshot[$key] !== $transfer->$key) {
                    throw PaymentTransferApprovalException::blocked('changed_transfer');
                }
            }
            if ($snapshot['reason'] !== ($transfer->metadata['note'] ?? null)) {
                throw PaymentTransferApprovalException::blocked('changed_transfer');
            }

            $recipient = new PaymentTransferRecipientEntity();
            $recipient->assign($snapshot['recipient']);

            return $recipient;
        });
    }
}
