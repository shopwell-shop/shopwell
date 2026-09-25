<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Sync;

use Shopwell\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class SyncService implements SyncServiceInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly EntitySearcherInterface $searcher,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly AclCriteriaValidator $criteriaValidator,
        private readonly SyncFkResolver $syncFkResolver,
        private readonly SyncMetricsInstrumentor $syncMetrics,
    ) {
    }

    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult
    {
        return $this->syncMetrics->measure($operations, $behavior, fn (): SyncResult => $this->doSync($operations, $context, $behavior));
    }

    /**
     * @param list<SyncOperation> $operations
     */
    private function doSync(array $operations, Context $context, SyncBehavior $behavior): SyncResult
    {
        ReplicaConnection::ensurePrimary();

        $context = clone $context;

        $this->loopOperations($operations, $context);

        if ($behavior->getSkipIndexers() !== []) {
            $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, new ArrayEntity(['skips' => $behavior->getSkipIndexers()]));
        }
        if ($behavior->getOnlyIndexers() !== []) {
            $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_ONLY, new ArrayEntity(['onlies' => $behavior->getOnlyIndexers()]));
        }

        if (
            $behavior->getIndexingBehavior() !== null
            && \in_array($behavior->getIndexingBehavior(), [EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE], true)
        ) {
            $context->addState($behavior->getIndexingBehavior());
        }

        $result = $this->writer->sync($operations, WriteContext::createFromContext($context));

        $writes = EntityWrittenContainerEvent::createWithWrittenEvents($result->getWritten(), $context, []);
        $deletes = EntityWrittenContainerEvent::createWithDeletedEvents($result->getDeleted(), $context, []);

        if ($deletes->getEvents() !== null) {
            $writes->addEvent(...$deletes->getEvents()->getElements());
        }

        $context->scope(Context::SYSTEM_SCOPE, fn () => $this->eventDispatcher->dispatch($writes), [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        $ids = $this->getWrittenEntities($result->getWritten());

        $deleted = $this->getWrittenEntitiesByEvent($deletes);

        $notFound = $this->getWrittenEntities($result->getNotFound());

        return new SyncResult($ids, $notFound, $deleted);
    }

    /**
     * @param array<string, EntityWriteResult[]> $grouped
     *
     * @return array<string, array<int, mixed>>
     */
    private function getWrittenEntities(array $grouped): array
    {
        $mapped = [];

        foreach ($grouped as $entity => $results) {
            foreach ($results as $result) {
                $mapped[$entity][] = $result->getPrimaryKey();
            }
        }

        ksort($mapped);

        return $mapped;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function getWrittenEntitiesByEvent(EntityWrittenContainerEvent $result): array
    {
        $entities = [];

        foreach ($result->getEvents() ?? [] as $event) {
            $entity = $event->getEntityName();

            if (!isset($entities[$entity])) {
                $entities[$entity] = [];
            }

            $entities[$entity] = array_merge($entities[$entity], $event->getIds());
        }

        ksort($entities);

        return $entities;
    }

    /**
     * Function to loop through all operations and provide some special handling for wildcard operations, or other short hands
     *
     * @param list<SyncOperation> $operations
     */
    private function loopOperations(array $operations, Context $context): void
    {
        foreach ($operations as $operation) {
            if ($operation->getAction() === SyncOperation::ACTION_DELETE && $operation->hasCriteria()) {
                $this->handleCriteriaDelete($operation, $context);

                continue;
            }

            if ($operation->getAction() === SyncOperation::ACTION_UPSERT) {
                $resolved = $this->syncFkResolver->resolve($operation->getKey(), $operation->getEntity(), $operation->getPayload());

                $operation->replacePayload($resolved);
            }
        }
    }

    private function handleCriteriaDelete(SyncOperation $operation, Context $context): void
    {
        $definition = $this->registry->getByEntityName($operation->getEntity());

        $filters = $this->criteriaBuilder->fromArray(['filter' => $operation->getCriteria()], new Criteria(), $definition, $context);

        $criteria = new Criteria();
        $criteria->addFilter(...$filters->getFilters());

        if ($criteria->getFilters() === []) {
            throw ApiException::invalidSyncCriteriaException($operation->getKey());
        }

        $missingPrivileges = $this->criteriaValidator->validate($definition->getEntityName(), $criteria, $context);
        if ($missingPrivileges !== []) {
            throw ApiException::missingPrivileges($missingPrivileges);
        }

        $ids = $this->searcher->search($definition, $criteria, $context);

        $values = \array_values($ids->getIds());

        $primaries = $definition->getPrimaryKeys()->filterInstance(IdField::class);
        if (\count($primaries) === 1) {
            $first = $primaries->first();
            \assert($first instanceof IdField); // we already checked with the count
            $values = \array_map(static fn ($id) => [$first->getPropertyName() => $id], $values);
        }

        $operation->replacePayload($values);
    }
}
