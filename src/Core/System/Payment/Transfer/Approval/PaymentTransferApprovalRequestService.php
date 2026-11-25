<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalRequestServiceTest
 */
#[Package('framework')]
class PaymentTransferApprovalRequestService
{
    private const BUSINESS_TYPE = 'payment:transfer';

    public function __construct(
        private readonly PaymentTransferApprovalRequestStore $store,
        private readonly ApprovalRequestService $requests,
        private readonly PaymentTransferApprovalStore $legacyApprovals,
        private readonly PaymentTransferPresenter $presenter,
    ) {
    }

    public function submit(string $transferId, Context $context): ApprovalRequestEntity
    {
        return $this->store->transactional($transferId, function () use ($transferId, $context): ApprovalRequestEntity {
            $existing = $this->requests->find(self::BUSINESS_TYPE, $transferId, 1, $context);
            if ($existing !== null) {
                $this->requests->start($existing->getId(), $context);

                return $existing;
            }
            if ($this->legacyApprovals->get($transferId) !== null) {
                throw PaymentTransferApprovalException::blocked('approval_already_started');
            }
            $facts = $this->store->snapshot($transferId, requirePayoutEligibility: true);
            if ($facts['submitAttempts'] !== 0 || $facts['state'] !== PaymentTransferStates::STATE_CREATED) {
                throw PaymentTransferApprovalException::blocked('transfer_already_started');
            }
            $snapshot = $facts['snapshot'];
            $recipient = new PaymentTransferRecipientEntity();
            $recipient->assign($snapshot['recipient']);
            $label = trim(($recipient->holderName ?? '') . ' ' . $this->presenter->recipient($recipient)['maskedIdentity']);
            $formJson = json_encode([
                'amount' => $snapshot['amount'],
                'currency' => $snapshot['currency'],
                'recipient' => $label,
                'paymentAccount' => $facts['paymentAccountLabel'],
                'reason' => $snapshot['reason'],
            ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            $revision = Hasher::hash(json_encode($snapshot, \JSON_THROW_ON_ERROR), 'sha256');
            $request = $this->requests->create(self::BUSINESS_TYPE, $transferId, 1, $revision, $formJson, $context);
            $this->store->freeze($transferId, $request->getId(), $snapshot);
            $this->requests->start($request->getId(), $context);

            return $request;
        });
    }
}
