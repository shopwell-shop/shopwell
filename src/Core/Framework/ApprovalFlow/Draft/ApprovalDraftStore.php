<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft\ApprovalDraftServiceTest
 */
#[Package('framework')]
class ApprovalDraftStore
{
    /**
     * @param EntityRepository<ApprovalFlowCollection> $definitionRepository
     * @param EntityRepository<ApprovalDraftCollection> $draftRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $definitionRepository,
        private readonly EntityRepository $draftRepository,
    ) {
    }

    public function create(string $definitionId, string $name, string $graphJson, Context $context): void
    {
        RetryableTransaction::transactional($this->connection, function () use ($definitionId, $name, $graphJson, $context): void {
            $context->scope(ApprovalDraftWriteGuard::WRITE_SCOPE, function (Context $writeContext) use ($definitionId, $name, $graphJson): void {
                $this->definitionRepository->create([[
                    'id' => $definitionId,
                    'name' => $name,
                ]], $writeContext);
                $this->draftRepository->create([[
                    'definitionId' => $definitionId,
                    'revision' => 1,
                    'graphJson' => $graphJson,
                ]], $writeContext);
            });
        });
    }

    public function read(string $definitionId, Context $context): ApprovalDraftEntity
    {
        $draft = $this->draftRepository->search(new Criteria([$definitionId]), $context)->first();

        if ($draft === null) {
            throw ApprovalFlowException::draftNotFound($definitionId);
        }

        return $draft;
    }

    /**
     * @template T
     *
     * @param \Closure(string, int): array{graphJson: string, result: T} $edit
     *
     * @return T
     */
    public function update(string $definitionId, int $expectedRevision, Context $context, \Closure $edit): mixed
    {
        return RetryableTransaction::transactional($this->connection, function () use ($definitionId, $expectedRevision, $context, $edit): mixed {
            // A locking read sees the current row even if an enclosing transaction already has a read snapshot.
            $draft = $this->connection->fetchAssociative(
                'SELECT revision, graph_json FROM approval_flow_draft WHERE definition_id = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($definitionId)],
            );

            if ($draft === false) {
                throw ApprovalFlowException::draftNotFound($definitionId);
            }

            $revision = (int) $draft['revision'];
            if ($expectedRevision !== $revision) {
                throw ApprovalFlowException::draftRevisionConflict($definitionId, $expectedRevision, $revision);
            }

            $nextRevision = $revision + 1;
            $edited = $edit((string) $draft['graph_json'], $nextRevision);

            $context->scope(ApprovalDraftWriteGuard::WRITE_SCOPE, fn (Context $writeContext) => $this->draftRepository->update([[
                'definitionId' => $definitionId,
                'revision' => $nextRevision,
                'graphJson' => $edited['graphJson'],
            ]], $writeContext));

            return $edited['result'];
        });
    }
}
