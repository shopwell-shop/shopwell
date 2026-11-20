<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledApprovalRelease;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Release\ApprovalReleaseServiceTest
 */
#[Package('framework')]
class ApprovalReleaseStore
{
    /**
     * @param EntityRepository<ApprovalReleaseCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $repository,
    ) {
    }

    /**
     * @param \Closure(string): CompiledApprovalRelease $compile
     */
    public function publish(string $definitionId, int $expectedRevision, string $expectedHash, Context $context, \Closure $compile): PublishedApprovalRelease
    {
        return RetryableTransaction::transactional($this->connection, function () use ($definitionId, $expectedRevision, $expectedHash, $context, $compile): PublishedApprovalRelease {
            // Share the draft writer's row lock and current read, including inside an older outer read snapshot.
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
            $content = $compile((string) $draft['graph_json']);
            if (!hash_equals($expectedHash, $content->hash)) {
                throw ApprovalFlowException::publicationContentConflict($definitionId, $expectedHash, $content->hash);
            }

            // A normal consistent read could miss a previous publisher after this transaction waited on the draft lock.
            $existing = $this->connection->fetchAssociative(
                'SELECT id, draft_revision FROM approval_flow_release WHERE definition_id = :id AND content_hash = :hash FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($definitionId), 'hash' => $content->hash],
            );
            if ($existing !== false) {
                return new PublishedApprovalRelease(Uuid::fromBytesToHex($existing['id']), $definitionId, (int) $existing['draft_revision'], $content->hash);
            }

            $id = Uuid::randomHex();
            $context->scope(ApprovalReleaseWriteGuard::WRITE_SCOPE, fn (Context $writeContext) => $this->repository->create([[
                'id' => $id,
                'definitionId' => $definitionId,
                'draftRevision' => $revision,
                'graphJson' => $content->graphJson,
                'contractJson' => $content->contractJson,
                'graphPlanJson' => $content->graphPlanJson,
                'nodePlanJson' => $content->nodePlanJson,
                'dependenciesJson' => $content->dependenciesJson,
                'contentHash' => $content->hash,
            ]], $writeContext));

            return new PublishedApprovalRelease($id, $definitionId, $revision, $content->hash);
        });
    }

    /**
     * @return array{graphJson: string, contractJson: string, graphPlanJson: string, nodePlanJson: string, dependenciesJson: string, contentHash: string}
     */
    public function readContent(string $releaseId): array
    {
        // A current read also sees releases published after an enclosing repeatable-read snapshot.
        $stored = $this->connection->fetchAssociative(
            'SELECT graph_json, contract_json, graph_plan_json, node_plan_json, dependencies_json, content_hash
             FROM approval_flow_release WHERE id = :id LOCK IN SHARE MODE',
            ['id' => Uuid::fromHexToBytes($releaseId)],
        );
        if ($stored === false) {
            throw ApprovalFlowException::releaseNotFound($releaseId);
        }

        return [
            'graphJson' => $stored['graph_json'],
            'contractJson' => $stored['contract_json'],
            'graphPlanJson' => $stored['graph_plan_json'],
            'nodePlanJson' => $stored['node_plan_json'],
            'dependenciesJson' => $stored['dependencies_json'],
            'contentHash' => $stored['content_hash'],
        ];
    }

    public function read(string $releaseId, Context $context): ApprovalReleaseEntity
    {
        return $this->repository->search(new Criteria([$releaseId]), $context)->first() ?? throw ApprovalFlowException::releaseNotFound($releaseId);
    }
}
