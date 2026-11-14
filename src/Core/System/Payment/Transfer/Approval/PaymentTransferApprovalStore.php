<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalStoreTest
 */
#[Package('framework')]
class PaymentTransferApprovalStore
{
    /**
     * @param EntityRepository<PaymentTransferApprovalCollection> $repository
     */
    public function __construct(private readonly Connection $connection, private readonly EntityRepository $repository)
    {
    }

    public function get(string $transferId): ?PaymentTransferApprovalEntity
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM payment_transfer_approval WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($transferId)], ['id' => ParameterType::BINARY]);
        if ($row === false) {
            return null;
        }
        $approval = new PaymentTransferApprovalEntity();
        $approval->id = $transferId;
        $approval->releaseId = Uuid::fromBytesToHex($row['release_id']);
        $approval->instanceId = Uuid::fromBytesToHex($row['instance_id']);
        $approval->initiatorId = Uuid::fromBytesToHex($row['initiator_id']);
        $approval->snapshot = json_decode($row['snapshot'], true, flags: \JSON_THROW_ON_ERROR);

        return $approval;
    }

    /**
     * @param list<string> $transferIds
     */
    public function getMany(array $transferIds, Context $context): PaymentTransferApprovalCollection
    {
        if ($transferIds === []) {
            return new PaymentTransferApprovalCollection();
        }

        return $this->repository->search(new Criteria($transferIds), $context)->getEntities();
    }

    public function create(PaymentTransferApprovalEntity $approval): void
    {
        $this->repository->create([[
            'id' => $approval->id,
            'releaseId' => $approval->releaseId,
            'instanceId' => $approval->instanceId,
            'initiatorId' => $approval->initiatorId,
            'snapshot' => $approval->snapshot,
        ]], Context::createDefaultContext());
    }

    public function lock(string $transferId): void
    {
        if ($this->connection->fetchOne('SELECT id FROM payment_transfer WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($transferId)], ['id' => ParameterType::BINARY]) === false) {
            throw PaymentTransferApprovalException::blocked('missing_transfer');
        }
    }
}
