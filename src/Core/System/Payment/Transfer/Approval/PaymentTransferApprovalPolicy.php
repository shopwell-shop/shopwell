<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * @internal
 */
#[Package('framework')]
final class PaymentTransferApprovalPolicy
{
    public function __construct(private readonly WorkflowDocumentCodec $codec)
    {
    }

    public function validate(WorkflowReleaseEntity $release): void
    {
        $document = $this->codec->decode($release->getDocument());
        $nodes = [];
        $starts = [];
        foreach ($document->nodes as $node) {
            $nodes[$node->id] = $node;
            if ($node->type === 'core:start') {
                $starts[] = $node;
            }
        }
        if (\count($starts) !== 1) {
            throw PaymentTransferApprovalException::blocked('incompatible_workflow');
        }
        $config = (array) $starts[0]->config;
        $fields = $config['formFields'] ?? [];
        if (!\is_array($fields) || \count($fields) !== 5) {
            throw PaymentTransferApprovalException::blocked('incompatible_form');
        }
        $expected = ['amount' => ['number', true], 'currency' => ['text', true], 'recipient' => ['text', true], 'paymentAccount' => ['text', true], 'reason' => ['text', false]];
        foreach ($fields as $raw) {
            $field = (array) $raw;
            $key = $field['key'] ?? '';
            if (!\is_string($key) || !isset($expected[$key]) || [$field['type'] ?? null, $field['required'] ?? false] !== $expected[$key]) {
                throw PaymentTransferApprovalException::blocked('incompatible_form');
            }
            unset($expected[$key]);
        }
        $edges = [];
        foreach ($document->connections as $edge) {
            $edges[$edge->source][] = $edge->target;
        }
        // Explore all paths until their first approval. No executable route may bypass it.
        $pending = [$starts[0]->id];
        $visited = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            $node = $nodes[$id] ?? null;
            if ($node === null || $node->type === 'core:end') {
                throw PaymentTransferApprovalException::blocked('approval_bypass');
            }
            if ($node->type === 'core:approval' || isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            if (($edges[$id] ?? []) === []) {
                throw PaymentTransferApprovalException::blocked('incomplete_workflow');
            }
            array_push($pending, ...$edges[$id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(PaymentTransferEntity $transfer, PaymentTransferRecipientEntity $recipient): array
    {
        return [
            'paymentAppId' => $transfer->paymentAppId,
            'accountId' => $transfer->accountId,
            'recipientId' => $transfer->recipientId,
            'outNo' => $transfer->outNo,
            'provider' => $transfer->provider,
            'channel' => $transfer->channel,
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'channelParams' => $transfer->channelParams,
            'reason' => $transfer->metadata['note'] ?? null,
            'recipient' => [
                'id' => $recipient->id,
                'paymentAppId' => $recipient->paymentAppId,
                'provider' => $recipient->provider,
                'identityType' => $recipient->identityType,
                'identityValue' => $recipient->identityValue,
                'identityHash' => $recipient->identityHash,
                'holderName' => $recipient->holderName,
                'providerAccountId' => $recipient->providerAccountId,
                'bankInfo' => $recipient->bankInfo,
                'onboardingStatus' => $recipient->onboardingStatus,
                'payoutEnabled' => $recipient->payoutEnabled,
            ],
        ];
    }

    public function recipient(PaymentTransferApprovalEntity $approval, PaymentTransferEntity $transfer): PaymentTransferRecipientEntity
    {
        $snapshot = $approval->snapshot;
        foreach (['paymentAppId', 'accountId', 'recipientId', 'outNo', 'provider', 'channel', 'amount', 'currency', 'channelParams'] as $key) {
            if (!\array_key_exists($key, $snapshot) || $snapshot[$key] !== $transfer->$key) {
                throw PaymentTransferApprovalException::blocked('changed_transfer');
            }
        }
        if (($snapshot['reason'] ?? null) !== ($transfer->metadata['note'] ?? null)) {
            throw PaymentTransferApprovalException::blocked('changed_transfer');
        }
        if (!\is_array($snapshot['recipient'] ?? null)) {
            throw PaymentTransferApprovalException::blocked('missing_recipient_snapshot');
        }
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->assign($snapshot['recipient']);

        return $recipient;
    }
}
