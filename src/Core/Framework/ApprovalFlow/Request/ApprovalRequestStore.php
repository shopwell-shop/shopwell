<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Request;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request\ApprovalRequestServiceTest
 */
#[Package('framework')]
class ApprovalRequestStore
{
    /**
     * @param EntityRepository<ApprovalRequestCollection> $requests
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $requests,
        private readonly ApprovalInstanceWriteGuard $guard,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function transactional(\Closure $operation): mixed
    {
        return RetryableTransaction::transactional($this->connection, $operation);
    }

    public function create(ApprovalBindingEntity $binding, string $businessId, int $round, string $businessRevision, string $formJson, string $initiatorId, Context $context): ApprovalRequestEntity
    {
        $data = [
            'id' => Uuid::randomHex(),
            'businessType' => $binding->getBusinessType(),
            'businessId' => $businessId,
            'submissionRound' => $round,
            'businessRevision' => $businessRevision,
            'bindingId' => $binding->getId(),
            'instanceId' => Uuid::randomHex(),
            'initiatorId' => $initiatorId,
            'formJson' => $formJson,
            'createdAt' => $this->clock->now(),
        ];
        $this->guard->create($context, function (Context $writeContext) use ($data, $binding): void {
            $this->requests->create([array_merge($data, [
                'instance' => [
                    'id' => $data['instanceId'],
                    'releaseId' => $binding->getReleaseId(),
                    'status' => ApprovalInstanceStatus::Draft->value,
                    'aggregateVersion' => 1,
                ],
            ])], $writeContext);
        });
        $request = new ApprovalRequestEntity();
        $request->assign($data);

        return $request;
    }

    /**
     * Current read after the business owner has serialized this submission identity.
     */
    public function find(string $type, string $businessId, int $round): ?ApprovalRequestEntity
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM approval_flow_request WHERE business_type = :type AND business_id = :business AND submission_round = :round FOR UPDATE',
            ['type' => $type, 'business' => $businessId, 'round' => $round],
        );
        if ($row === false) {
            return null;
        }
        $request = new ApprovalRequestEntity();
        $request->assign([
            'id' => Uuid::fromBytesToHex($row['id']),
            'businessType' => $row['business_type'],
            'businessId' => $row['business_id'],
            'submissionRound' => (int) $row['submission_round'],
            'businessRevision' => $row['business_revision'],
            'bindingId' => Uuid::fromBytesToHex($row['binding_id']),
            'instanceId' => Uuid::fromBytesToHex($row['instance_id']),
            'initiatorId' => Uuid::fromBytesToHex($row['initiator_id']),
            'formJson' => $row['form_json'],
            'createdAt' => new \DateTimeImmutable($row['created_at']),
        ]);

        return $request;
    }
}
