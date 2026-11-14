<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Draft;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Draft\Entity\WorkflowDraftCollection;
use Shopwell\Core\Framework\Workflow\Draft\Entity\WorkflowDraftEntity;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowDraftPersistenceTest
 */
#[Package('framework')]
final readonly class WorkflowDraftStore
{
    /**
     * @param EntityRepository<WorkflowDraftCollection> $repository
     */
    public function __construct(private Connection $connection, private EntityRepository $repository, private WorkflowDocumentCodec $codec)
    {
    }

    public function get(string $id, Context $context): WorkflowDraftEntity
    {
        $this->validateId($id);
        $draft = $this->repository->search(new Criteria([$id]), $context)->getEntities()->first();
        if (!$draft instanceof WorkflowDraftEntity) {
            throw WorkflowException::draftNotFound($id);
        }

        return $draft;
    }

    public function create(string $id, string $name, string $document, Context $context): WorkflowDraftEntity
    {
        $this->validate($id, $name, $document);
        try {
            return $this->connection->transactional(function () use ($id, $name, $document, $context): WorkflowDraftEntity {
                if ($this->connection->fetchOne('SELECT id FROM workflow_draft WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]) !== false) {
                    throw WorkflowException::draftConflict($id);
                }
                // Published revisions retain this identity even after their draft is deleted.
                if ($this->connection->fetchOne('SELECT id FROM workflow_release WHERE draft_id = :id LIMIT 1', ['id' => Uuid::fromHexToBytes($id)]) !== false) {
                    throw WorkflowException::draftConflict($id);
                }
                $context->scope(Context::SYSTEM_SCOPE, fn () => $this->repository->create([['id' => $id, 'name' => trim($name), 'revision' => 1, 'document' => $document]], $context));

                return $this->get($id, $context);
            });
        } catch (UniqueConstraintViolationException) {
            throw WorkflowException::draftConflict($id);
        }
    }

    public function update(string $id, string $name, string $document, int $expectedRevision, Context $context): WorkflowDraftEntity
    {
        $this->validate($id, $name, $document);

        return $this->connection->transactional(function () use ($id, $name, $document, $expectedRevision, $context): WorkflowDraftEntity {
            $this->lock($id, $expectedRevision);
            $context->scope(Context::SYSTEM_SCOPE, fn () => $this->repository->update([['id' => $id, 'name' => trim($name), 'document' => $document, 'revision' => $expectedRevision + 1]], $context));

            return $this->get($id, $context);
        });
    }

    public function delete(string $id, int $expectedRevision, Context $context): void
    {
        $this->validateId($id);
        $this->connection->transactional(function () use ($id, $expectedRevision, $context): void {
            $this->lock($id, $expectedRevision);
            $context->scope(Context::SYSTEM_SCOPE, fn () => $this->repository->delete([['id' => $id]], $context));
        });
    }

    private function lock(string $id, int $expectedRevision): void
    {
        $revision = $this->connection->fetchOne('SELECT revision FROM workflow_draft WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($id)]);
        if ($revision === false) {
            throw WorkflowException::draftNotFound($id);
        }
        if ($expectedRevision < 1 || (int) $revision !== $expectedRevision) {
            throw WorkflowException::draftConflict($id);
        }
    }

    private function validate(string $id, string $name, string $document): void
    {
        $this->validateId($id);
        if (trim($name) === '' || mb_strlen($name) > 255) {
            throw WorkflowException::invalidDocument('/name', 'Provide a name of 1 to 255 characters.');
        }
        // Incomplete topology and unavailable plugin types are valid drafts; only the document format is required.
        $this->codec->decode($document);
    }

    private function validateId(string $id): void
    {
        if (!Uuid::isValid($id)) {
            throw WorkflowException::invalidDocument('/id', 'Expected a UUID.');
        }
    }
}
