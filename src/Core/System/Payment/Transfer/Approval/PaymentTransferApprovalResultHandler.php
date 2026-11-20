<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResult;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalResultHandlerTest
 */
#[Package('framework')]
class PaymentTransferApprovalResultHandler implements ApprovalResultHandler
{
    /**
     * @param EntityRepository<PaymentTransferApprovalResultCollection> $results
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $results,
        private readonly PaymentTransferApprovalRequestStore $requests,
    ) {
    }

    public function getType(): string
    {
        return 'payment:transfer';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function handle(ApprovalResult $result, ApprovalRequestEntity $request, Context $context): void
    {
        $transferId = $request->getBusinessId();
        $this->requests->transactional($transferId, function () use ($transferId, $result, $request, $context): void {
            $id = Uuid::fromHexToBytes($transferId);
            $existing = $this->connection->fetchAssociative('SELECT request_id, result_id FROM payment_transfer_approval_result WHERE id = :id FOR UPDATE', ['id' => $id]);
            if ($existing !== false) {
                if ($existing['request_id'] !== Uuid::fromHexToBytes($request->getId()) || $existing['result_id'] !== Uuid::fromHexToBytes($result->id)) {
                    throw PaymentTransferApprovalException::resultConflict($request->getId(), 'Another approval result has already been applied.');
                }

                return;
            }

            $requestId = $this->connection->fetchOne('SELECT request_id FROM payment_transfer_approval_request WHERE id = :id LOCK IN SHARE MODE', ['id' => $id]);
            if ($requestId !== Uuid::fromHexToBytes($request->getId())) {
                throw PaymentTransferApprovalException::resultConflict($request->getId(), 'The transfer is bound to another approval request.');
            }
            $facts = $this->requests->snapshot($transferId);
            $revision = Hasher::hash(json_encode($facts['snapshot'], \JSON_THROW_ON_ERROR), 'sha256');
            if ($facts['submitAttempts'] !== 0 || $facts['state'] !== PaymentTransferStates::STATE_CREATED || !hash_equals($request->getBusinessRevision(), $revision)) {
                throw PaymentTransferApprovalException::resultConflict($request->getId(), 'The transfer no longer matches the submitted revision.');
            }

            // This immutable receipt is both the business decision and its inbox deduplication record.
            $this->results->create([[
                'id' => $transferId,
                'requestId' => $request->getId(),
                'resultId' => $result->id,
                'outcome' => $result->outcome->value,
            ]], $context);
        });
    }
}
