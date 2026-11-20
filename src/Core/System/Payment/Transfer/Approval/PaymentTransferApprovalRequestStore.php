<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Locks authoritative transfer inputs and persists private payout evidence separately from the approval form.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalRequestServiceTest
 */
#[Package('framework')]
class PaymentTransferApprovalRequestStore
{
    /**
     * @param EntityRepository<PaymentTransferApprovalRequestCollection> $requests
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $requests,
        private readonly PaymentTransferApprovalStore $approvals,
    ) {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $submit
     *
     * @return T
     */
    public function transactional(string $transferId, \Closure $submit): mixed
    {
        return $this->approvals->submitting($transferId, function () use ($transferId, $submit): mixed {
            return RetryableTransaction::transactional($this->connection, function () use ($transferId, $submit): mixed {
                if ($this->connection->fetchOne('SELECT id FROM payment_transfer WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($transferId)]) === false) {
                    throw PaymentTransferApprovalException::blocked('missing_transfer');
                }

                return $submit();
            });
        });
    }

    /**
     * A missing outcome means the new approval has not delivered its business decision yet.
     *
     * @return array{revision: string, outcome: ApprovalOutcome|null}|null
     */
    public function getApproval(string $transferId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT request.business_revision, result.outcome FROM payment_transfer_approval_request evidence
             INNER JOIN approval_flow_request request ON request.id = evidence.request_id
             LEFT JOIN payment_transfer_approval_result result ON result.id = evidence.id AND result.request_id = evidence.request_id
             WHERE evidence.id = :id LOCK IN SHARE MODE',
            ['id' => Uuid::fromHexToBytes($transferId)],
        );
        if ($row === false) {
            return null;
        }

        return ['revision' => $row['business_revision'], 'outcome' => $row['outcome'] === null ? null : ApprovalOutcome::from($row['outcome'])];
    }

    /**
     * @return array{state: string, submitAttempts: int, paymentAccountLabel: string, snapshot: array<string, mixed>}
     */
    public function snapshot(string $transferId): array
    {
        // Current reads are necessary even when the caller entered with an older repeatable-read snapshot.
        $transfer = $this->connection->fetchAssociative(
            'SELECT t.*, s.technical_name AS state FROM payment_transfer t
             INNER JOIN state_machine_state s ON s.id = t.status_id WHERE t.id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($transferId)],
        );
        if ($transfer === false) {
            throw PaymentTransferApprovalException::blocked('missing_transfer');
        }
        $recipient = $this->connection->fetchAssociative(
            'SELECT * FROM payment_transfer_recipient WHERE id = :id AND payment_app_id = :app FOR UPDATE',
            ['id' => $transfer['recipient_id'], 'app' => $transfer['payment_app_id']],
        );
        if ($recipient === false || !(bool) $recipient['payout_enabled'] || $recipient['provider'] !== $transfer['provider']) {
            throw PaymentTransferApprovalException::blocked('recipient_unavailable');
        }
        $account = $this->connection->fetchAssociative(
            'SELECT name, active, provider_technical_name FROM payment_account WHERE id = :id FOR UPDATE',
            ['id' => $transfer['account_id']],
        );
        if ($account === false || !(bool) $account['active'] || $account['provider_technical_name'] !== $transfer['provider']) {
            throw PaymentTransferApprovalException::blocked('payment_account_unavailable');
        }
        $metadata = $transfer['metadata'] === null ? null : json_decode($transfer['metadata'], associative: true, flags: \JSON_THROW_ON_ERROR);
        $snapshot = [
            'paymentAppId' => Uuid::fromBytesToHex($transfer['payment_app_id']),
            'accountId' => Uuid::fromBytesToHex($transfer['account_id']),
            'recipientId' => Uuid::fromBytesToHex($transfer['recipient_id']),
            'outNo' => $transfer['out_no'],
            'provider' => $transfer['provider'],
            'channel' => $transfer['channel'],
            'amount' => (int) $transfer['amount'],
            'currency' => $transfer['currency'],
            'channelParams' => $transfer['channel_params'] === null ? null : json_decode($transfer['channel_params'], associative: true, flags: \JSON_THROW_ON_ERROR),
            'reason' => $metadata['note'] ?? null,
            'recipient' => [
                'id' => Uuid::fromBytesToHex($recipient['id']),
                'paymentAppId' => Uuid::fromBytesToHex($recipient['payment_app_id']),
                'provider' => $recipient['provider'],
                'identityType' => $recipient['identity_type'],
                'identityValue' => $recipient['identity_value'],
                'identityHash' => $recipient['identity_hash'],
                'holderName' => $recipient['holder_name'],
                'providerAccountId' => $recipient['provider_account_id'],
                'bankInfo' => $recipient['bank_info'] === null ? null : json_decode($recipient['bank_info'], associative: true, flags: \JSON_THROW_ON_ERROR),
                'onboardingStatus' => $recipient['onboarding_status'],
                'payoutEnabled' => (bool) $recipient['payout_enabled'],
            ],
        ];

        return [
            'state' => $transfer['state'],
            'submitAttempts' => (int) $transfer['submit_attempts'],
            'paymentAccountLabel' => $account['name'],
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function freeze(string $transferId, string $requestId, array $snapshot): void
    {
        $this->requests->create([['id' => $transferId, 'requestId' => $requestId, 'snapshot' => $snapshot]], Context::createDefaultContext());
    }
}
