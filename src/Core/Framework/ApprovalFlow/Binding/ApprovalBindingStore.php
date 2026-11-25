<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding\ApprovalBindingServiceTest
 */
#[Package('framework')]
class ApprovalBindingStore
{
    /**
     * @var array<string, true>
     */
    private array $activeWrites = [];

    /**
     * @param EntityRepository<ApprovalBindingCollection> $bindings
     * @param EntityRepository<ApprovalBindingHeadCollection> $heads
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $bindings,
        private readonly EntityRepository $heads,
        private readonly ApprovalBindingWriteGuard $guard,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param \Closure(): void $verify Validates the release while the same-type operation guard is held.
     */
    public function save(ApprovalBusinessTypeSpecification $businessType, string $releaseId, int $expectedRevision, Context $context, \Closure $verify): ApprovalBindingEntity
    {
        return $this->writeBinding($businessType->type, function () use ($businessType, $releaseId, $expectedRevision, $context, $verify): ApprovalBindingEntity {
            $head = $this->initializeHead($businessType->type);
            $revision = (int) $head['latest_revision'];
            if ($expectedRevision !== $revision) {
                throw ApprovalFlowException::bindingRevisionConflict($businessType->type, $expectedRevision, $revision);
            }
            $verify();

            $this->saveRevision($businessType, $releaseId, $head['id'], $revision + 1, $context);

            return $this->read($businessType->type, $revision + 1, $context);
        });
    }

    /**
     * @param \Closure(string, string, string): void $verify Receives the frozen business version, form bytes and release ID.
     */
    public function activate(string $type, int $revision, int $expectedVersion, Context $context, \Closure $verify): ApprovalBindingHeadEntity
    {
        return $this->writeBinding($type, function () use ($type, $revision, $expectedVersion, $context, $verify): ApprovalBindingHeadEntity {
            $head = $this->lockHead($type);
            $version = (int) $head['version'];
            if ($expectedVersion !== $version) {
                throw ApprovalFlowException::bindingVersionConflict($type, $expectedVersion, $version);
            }
            // Current read: a caller's older repeatable-read snapshot may predate this saved revision.
            $binding = $this->connection->fetchAssociative(
                'SELECT business_version, form_schema, release_id FROM approval_flow_binding WHERE business_type = :type AND revision = :revision FOR UPDATE',
                ['type' => $type, 'revision' => $revision],
            );
            if ($binding === false) {
                throw ApprovalFlowException::bindingNotFound($type, $revision);
            }
            $verify((string) $binding['business_version'], (string) $binding['form_schema'], Uuid::fromBytesToHex($binding['release_id']));
            $this->selectRevision($head['id'], $revision, $version + 1);

            return $this->head($type, $context);
        });
    }

    /**
     * @param \Closure(): void $verify Validates the selected release before saving or activating it.
     */
    public function activateRelease(ApprovalBusinessTypeSpecification $businessType, string $releaseId, int $expectedRevision, int $expectedVersion, Context $context, \Closure $verify): ApprovalBindingHeadEntity
    {
        $type = $businessType->type;

        return $this->writeBinding($type, function () use ($businessType, $type, $releaseId, $expectedRevision, $expectedVersion, $context, $verify): ApprovalBindingHeadEntity {
            $head = $this->initializeHead($type);
            $revision = (int) $head['latest_revision'];
            $version = (int) $head['version'];
            if ($expectedRevision !== $revision) {
                throw ApprovalFlowException::bindingRevisionConflict($type, $expectedRevision, $revision);
            }
            if ($expectedVersion !== $version) {
                throw ApprovalFlowException::bindingVersionConflict($type, $expectedVersion, $version);
            }
            $verify();
            $this->saveRevision($businessType, $releaseId, $head['id'], $revision + 1, $context);
            $this->selectRevision($head['id'], $revision + 1, $version + 1);

            return $this->head($type, $context);
        });
    }

    public function read(string $type, int $revision, Context $context): ApprovalBindingEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('businessType', $type), new EqualsFilter('revision', $revision));

        return $this->bindings->search($criteria, $context)->first() ?? throw ApprovalFlowException::bindingNotFound($type, $revision);
    }

    public function head(string $type, Context $context): ApprovalBindingHeadEntity
    {
        return $this->findHead($type, $context) ?? throw ApprovalFlowException::activeBindingNotFound($type);
    }

    public function findHead(string $type, Context $context): ?ApprovalBindingHeadEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('businessType', $type));

        return $this->heads->search($criteria, $context)->first();
    }

    public function active(string $type, Context $context): ApprovalBindingEntity
    {
        $head = $this->head($type, $context);
        $revision = $head->getActiveRevision();
        if ($revision === null) {
            throw ApprovalFlowException::activeBindingNotFound($type);
        }

        return $this->read($type, $revision, $context);
    }

    public function activeForSubmission(string $type): ApprovalBindingEntity
    {
        if (!$this->connection->isTransactionActive()) {
            throw ApprovalFlowException::requestTransactionRequired();
        }
        // Hold the selected head until the request and business freeze commit together.
        $row = $this->connection->fetchAssociative(
            'SELECT b.* FROM approval_flow_binding_head h
             INNER JOIN approval_flow_binding b ON b.business_type = h.business_type AND b.revision = h.active_revision
             WHERE h.business_type = :type LOCK IN SHARE MODE',
            ['type' => $type],
        );
        if ($row === false) {
            throw ApprovalFlowException::activeBindingNotFound($type);
        }
        $binding = new ApprovalBindingEntity();
        $binding->assign([
            'id' => Uuid::fromBytesToHex($row['id']),
            'businessType' => $row['business_type'],
            'businessVersion' => $row['business_version'],
            'revision' => (int) $row['revision'],
            'releaseId' => Uuid::fromBytesToHex($row['release_id']),
            'formSchemaId' => $row['form_schema_id'],
            'formSchemaHash' => $row['form_schema_hash'],
            'formSchema' => $row['form_schema'],
            'createdById' => $row['created_by_id'] === null ? null : Uuid::fromBytesToHex($row['created_by_id']),
            'createdAt' => new \DateTimeImmutable($row['created_at']),
        ]);

        return $binding;
    }

    /**
     * @return array{id: string, latest_revision: int|string, version: int|string}
     */
    private function initializeHead(string $type): array
    {
        // The unique type coordinates the first save too; a losing initializer waits for the current writer.
        $this->connection->executeStatement(
            'INSERT INTO approval_flow_binding_head (id, business_type, latest_revision, active_revision, version, created_at)
             VALUES (:id, :type, 0, NULL, 0, :createdAt) ON DUPLICATE KEY UPDATE business_type = business_type',
            ['id' => Uuid::randomBytes(), 'type' => $type, 'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
        );

        return $this->lockHead($type);
    }

    private function saveRevision(ApprovalBusinessTypeSpecification $businessType, string $releaseId, string $headId, int $revision, Context $context): void
    {
        $this->guard->write($context, function (Context $writeContext) use ($businessType, $releaseId, $revision): void {
            $this->bindings->create([[
                'id' => Uuid::randomHex(),
                'businessType' => $businessType->type,
                'businessVersion' => $businessType->version,
                'revision' => $revision,
                'releaseId' => $releaseId,
                'formSchemaId' => $businessType->formSchemaId,
                'formSchemaHash' => $businessType->formSchemaHash,
                'formSchema' => $businessType->formSchema,
            ]], $writeContext);
        });
        $this->connection->executeStatement(
            'UPDATE approval_flow_binding_head SET latest_revision = :revision, updated_at = :updatedAt WHERE id = :id',
            ['revision' => $revision, 'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'id' => $headId],
        );
    }

    private function selectRevision(string $headId, int $revision, int $version): void
    {
        $this->connection->executeStatement(
            'UPDATE approval_flow_binding_head SET active_revision = :revision, version = :version, updated_at = :updatedAt WHERE id = :id',
            ['revision' => $revision, 'version' => $version, 'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'id' => $headId],
        );
    }

    /**
     * @template T
     *
     * @param \Closure(): T $write
     *
     * @return T
     */
    private function writeBinding(string $type, \Closure $write): mixed
    {
        // Database locks are reentrant on the same connection; event callbacks must not start another change.
        if (isset($this->activeWrites[$type])) {
            throw ApprovalFlowException::bindingWriteInProgress($type);
        }
        $this->activeWrites[$type] = true;
        try {
            return RetryableTransaction::transactional($this->connection, $write);
        } finally {
            unset($this->activeWrites[$type]);
        }
    }

    /**
     * @return array{id: string, latest_revision: int|string, version: int|string}
     */
    private function lockHead(string $type): array
    {
        $head = $this->connection->fetchAssociative(
            'SELECT id, latest_revision, version FROM approval_flow_binding_head WHERE business_type = :type FOR UPDATE',
            ['type' => $type],
        );
        if ($head === false) {
            throw ApprovalFlowException::activeBindingNotFound($type);
        }

        return ['id' => $head['id'], 'latest_revision' => $head['latest_revision'], 'version' => $head['version']];
    }
}
