<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseCollection;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal Transactional persistence boundary for immutable publication snapshots.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowPublicationTest
 */
#[Package('framework')]
final readonly class WorkflowReleaseStore
{
    /**
     * @param EntityRepository<WorkflowReleaseCollection> $repository
     */
    public function __construct(
        private Connection $connection,
        private WorkflowDraftStore $drafts,
        private EntityRepository $repository,
        private WorkflowDocumentCodec $codec,
        private WorkflowGraphDiagnostics $diagnostics,
        private WorkflowPublisherRegistry $publishers,
        private EventDispatcherInterface $events = new EventDispatcher(),
    ) {
    }

    public function publish(string $draftId, int $expectedRevision, string $publisher, Context $context): WorkflowReleaseEntity
    {
        $this->validateId($draftId);
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null || !$context->isAllowed('workflow:publish')) {
            throw WorkflowException::missingPublicationPrivilege('workflow:publish');
        }
        $target = $this->publishers->get($publisher);
        foreach ($target->privileges() as $privilege) {
            if (!$context->isAllowed($privilege)) {
                throw WorkflowException::missingPublicationPrivilege($privilege);
            }
        }

        return $this->connection->transactional(function () use ($draftId, $expectedRevision, $publisher, $target, $source, $context): WorkflowReleaseEntity {
            $revision = $this->connection->fetchOne('SELECT revision FROM workflow_draft WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($draftId)]);
            if ($revision === false) {
                throw WorkflowException::draftNotFound($draftId);
            }
            if ($expectedRevision < 1 || (int) $revision !== $expectedRevision) {
                throw WorkflowException::draftConflict($draftId);
            }
            $criteria = (new Criteria())->addFilter(new EqualsFilter('draftId', $draftId), new EqualsFilter('revision', $expectedRevision), new EqualsFilter('publisher', $publisher))->setLimit(1);
            $existing = $this->repository->search($criteria, $context)->first();
            if ($existing instanceof WorkflowReleaseEntity) {
                return $existing;
            }

            $draft = $this->drafts->get($draftId, $context);
            $document = $this->codec->decode($draft->getDocument());
            if ($this->diagnostics->analyze($document) !== []) {
                throw WorkflowException::publicationRejected('invalid_graph');
            }
            $payload = [
                'id' => Uuid::randomHex(),
                'draftId' => $draftId,
                'revision' => $expectedRevision,
                'name' => $draft->getName(),
                'publisher' => $publisher,
                'publishedBy' => $source->getUserId(),
                'document' => $draft->getDocument(),
            ];
            $release = new WorkflowReleaseEntity();
            $release->assign($payload);
            // The business target and original snapshot commit together. Executions start in a separate request.
            $target->publish($release, $context);
            $context->scope(Context::SYSTEM_SCOPE, fn () => $this->repository->create([$payload], $context));

            return $this->get($payload['id'], $context);
        });
    }

    public function get(string $id, Context $context): WorkflowReleaseEntity
    {
        $this->validateId($id);
        $release = $this->repository->search(new Criteria([$id]), $context)->first();
        if (!$release instanceof WorkflowReleaseEntity) {
            throw WorkflowException::releaseNotFound($id);
        }

        return $release;
    }

    /**
     * @param list<string> $ids
     */
    public function getMany(array $ids, Context $context): WorkflowReleaseCollection
    {
        if ($ids === []) {
            return new WorkflowReleaseCollection();
        }

        return $this->repository->search(new Criteria(array_values(array_unique($ids))), $context)->getEntities();
    }

    /**
     * @return EntitySearchResult<WorkflowReleaseCollection>
     */
    public function list(?string $draftId, int $limit, int $offset, Context $context): EntitySearchResult
    {
        $criteria = (new Criteria())->setLimit(max(1, min(100, $limit)))->setOffset(max(0, $offset));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING), new FieldSorting('id', FieldSorting::DESCENDING));
        if ($draftId !== null) {
            $this->validateId($draftId);
            $criteria->addFilter(new EqualsFilter('draftId', $draftId));
        }

        return $this->repository->search($criteria, $context);
    }

    /**
     * Group before pagination: historical releases remain readable but never duplicate a request type.
     *
     * @return array{releases: WorkflowReleaseCollection, total: int}
     */
    public function catalogue(int $limit, int $offset, Context $context): array
    {
        $event = new WorkflowRequestCatalogueEvent($context);
        $this->events->dispatch($event);
        $parameters = ['publishers' => array_map(static fn (WorkflowPublisher $publisher): string => $publisher->name(), $this->publishers->all())];
        $types = ['publishers' => ArrayParameterType::STRING];
        $ordinary = [];
        $enabled = [];
        foreach ($event->restrictedActions as $action => $releaseId) {
            $index = \count($ordinary);
            $probe = 'action' . $index;
            $parameters[$probe] = json_encode(['config' => ['requestAction' => $action]], \JSON_THROW_ON_ERROR);
            $match = "JSON_CONTAINS(r.document, :$probe, '$.nodes')";
            $ordinary[] = $match . ' = 0';
            if ($releaseId !== null) {
                $releaseParameter = 'release' . $index;
                $parameters[$releaseParameter] = Uuid::fromHexToBytes($releaseId);
                $enabled[] = "($match = 1 AND r.id = :$releaseParameter)";
            }
        }
        $latest = 'NOT EXISTS (SELECT 1 FROM workflow_release newer WHERE newer.draft_id = r.draft_id AND newer.publisher = r.publisher AND newer.revision > r.revision)';
        $ordinary[] = $latest;
        $eligible = '(' . implode(' AND ', $ordinary) . ')';
        if ($enabled !== []) {
            $eligible .= ' OR ' . implode(' OR ', $enabled);
        }
        $from = ' FROM workflow_release r INNER JOIN workflow_draft d ON d.id = r.draft_id WHERE r.publisher IN (:publishers) AND (' . $eligible . ')';
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)' . $from, $parameters, $types);
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(r.id))' . $from . ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $parameters, $types);
        $releases = $this->getMany($ids, $context);
        $releases->sortByIdArray($ids);

        return ['releases' => $releases, 'total' => $total];
    }

    private function validateId(string $id): void
    {
        if (!Uuid::isValid($id)) {
            throw WorkflowException::invalidDocument('/id', 'Expected a UUID.');
        }
    }
}
