<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;

/**
 * Maintains the `level`, `path` and `child_count` columns of the
 * `organization_unit` tree after write operations.
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitIndexer extends EntityIndexer
{
    final public const CHILD_COUNT_UPDATER = 'organization_unit.child-count';
    final public const TREE_UPDATER = 'organization_unit.tree';

    /**
     * @internal
     *
     * @param EntityRepository<OrganizationUnitCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly ChildCountUpdater $childCountUpdater,
        private readonly TreeUpdater $treeUpdater,
    ) {
    }

    public function getName(): string
    {
        return 'organization_unit.indexer';
    }

    public function getTotal(): int
    {
        return $this->getIterator(null)->fetchCount();
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->getIterator($offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $unitEvent = $event->getEventByEntityName(OrganizationUnitDefinition::ENTITY_NAME);

        if (!$unitEvent) {
            return null;
        }

        $ids = $unitEvent->getIds();

        if ($ids === []) {
            return null;
        }

        $parentIds = [];
        foreach ($unitEvent->getWriteResults() as $result) {
            $payload = $result->getPayload();
            if ($result->getOperation() === EntityWriteResult::OPERATION_UPDATE && !\array_key_exists('parentId', $payload)) {
                continue;
            }

            $oldParentId = $result->getExistence()?->getState()['parent_id'] ?? null;
            if (\is_string($oldParentId)) {
                $parentIds[] = Uuid::fromBytesToHex($oldParentId);
            }
            if (isset($payload['parentId']) && \is_string($payload['parentId'])) {
                $parentIds[] = $payload['parentId'];
            }
        }
        if ($parentIds !== []) {
            $this->childCountUpdater->update(
                OrganizationUnitDefinition::ENTITY_NAME,
                array_values(array_unique($parentIds)),
                $event->getContext(),
            );
        }

        return new EntityIndexingMessage(array_values(array_unique($ids)), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $context = $message->getContext();

        $this->childCountUpdater->update(OrganizationUnitDefinition::ENTITY_NAME, $ids, $context);
        $this->treeUpdater->batchUpdate($ids, OrganizationUnitDefinition::ENTITY_NAME, $context, !$message->isFullIndexing);
    }

    public function getOptions(): array
    {
        return [
            self::CHILD_COUNT_UPDATER,
            self::TREE_UPDATER,
        ];
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    private function getIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }
}
