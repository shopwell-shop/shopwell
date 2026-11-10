<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogCategory;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists the Blog category graph through the native DAL tree fields and transaction hooks.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Blog\BlogCategoryTreeTest
 */
#[Package('discovery')]
class BlogCategoryTree implements EventSubscriberInterface
{
    /**
     * @var \WeakMap<WriteContext, array<string, array{parentId: ?string, afterCategoryId: ?string}>>
     */
    private \WeakMap $pending;

    /**
     * @param EntityRepository<BlogCategoryCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $repository,
        private readonly TreeUpdater $treeUpdater,
        private readonly ChildCountUpdater $childCountUpdater,
    ) {
        $this->pending = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => ['validate', 100],
            PostWriteValidationEvent::class => 'persist',
        ];
    }

    public function move(string $id, ?string $parentId, ?string $afterCategoryId, Context $context): void
    {
        foreach (['id' => $id, 'parentId' => $parentId, 'afterCategoryId' => $afterCategoryId] as $field => $value) {
            if ($value !== null && !Uuid::isValid($value)) {
                throw BlogException::invalidFieldValueType($field, 'uuid|null', get_debug_type($value));
            }
        }
        $this->connection->transactional(function () use ($id, $parentId, $afterCategoryId, $context): void {
            $this->lock();
            $graph = $this->loadGraph();
            if (!isset($graph[$id])) {
                throw BlogException::categoryNotFound($id);
            }
            $this->repository->update([['id' => $id, 'parentId' => $parentId, 'afterCategoryId' => $afterCategoryId]], $context);
        });
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommandsForEntity(BlogCategoryEntity::ENTITY_NAME);
        if ($commands === []) {
            return;
        }
        $this->lock();
        $original = $this->loadGraph();
        $projected = $original;
        $changes = [];
        foreach ($commands as $command) {
            $id = $command->getDecodedPrimaryKey()['id'];
            if ($command instanceof DeleteCommand) {
                unset($projected[$id]);
                continue;
            }
            $node = $projected[$id] ?? ['parentId' => null, 'afterCategoryId' => null];
            $payload = $command->getPayload();
            if ($command->hasField('parent_id')) {
                $node['parentId'] = $payload['parent_id'] === null ? null : Uuid::fromBytesToHex($payload['parent_id']);
            }
            if ($command->hasField('after_category_id')) {
                $node['afterCategoryId'] = $payload['after_category_id'] === null ? null : Uuid::fromBytesToHex($payload['after_category_id']);
            }
            $projected[$id] = $node;
            $insert = $command instanceof InsertCommand;
            $parentChanged = isset($original[$id]) && $original[$id]['parentId'] !== $node['parentId'];
            if ($insert || $parentChanged || $command->hasField('after_category_id')) {
                $changes[$id] = ['append' => ($insert && $node['afterCategoryId'] === null) || ($parentChanged && !$command->hasField('after_category_id')), 'after' => $node['afterCategoryId']];
            }
        }
        foreach (array_unique([...array_keys($changes), ...array_keys($projected)]) as $id) {
            $seen = [];
            $cursor = $id;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    throw BlogException::categoryCycle($id);
                }
                if (!isset($projected[$cursor])) {
                    throw BlogException::categoryNotFound($cursor);
                }
                $seen[$cursor] = true;
                $cursor = $projected[$cursor]['parentId'];
            }
        }
        $groups = [];
        foreach ($this->orderedGroups($original) as $scope => $ids) {
            $groups[$scope] = array_values(array_filter($ids, static fn (string $id): bool => isset($projected[$id]) && !isset($changes[$id])));
        }
        while ($changes !== []) {
            $progress = false;
            foreach ($changes as $id => $change) {
                $scope = $projected[$id]['parentId'] ?? '';
                $groups[$scope] ??= [];
                $after = $change['append'] ? null : $change['after'];
                if ($after !== null && ($after === $id || !isset($projected[$after]) || $projected[$after]['parentId'] !== $projected[$id]['parentId'])) {
                    throw BlogException::categoryPositionInvalid($after);
                }
                $position = $after === null ? false : array_search($after, $groups[$scope], true);
                if (!$change['append'] && $after !== null && $position === false) {
                    continue;
                }
                if ($change['append']) {
                    $groups[$scope][] = $id;
                } else {
                    array_splice($groups[$scope], $position === false ? 0 : $position + 1, 0, [$id]);
                }
                unset($changes[$id]);
                $progress = true;
            }
            if (!$progress) {
                throw BlogException::categoryPositionInvalid((string) array_key_first($changes));
            }
        }
        foreach ($groups as $ids) {
            $previous = null;
            foreach ($ids as $id) {
                $projected[$id] = ['parentId' => $projected[$id]['parentId'], 'afterCategoryId' => $previous];
                $previous = $id;
            }
        }
        $this->pending[$event->getWriteContext()] = $projected;
    }

    public function persist(PostWriteValidationEvent $event): void
    {
        $writeContext = $event->getWriteContext();
        if (!isset($this->pending[$writeContext])) {
            return;
        }
        $graph = $this->pending[$writeContext];
        unset($this->pending[$writeContext]);
        $stored = $this->loadGraph();
        $roots = [];
        foreach ($graph as $id => $node) {
            if (($stored[$id]['afterCategoryId'] ?? null) !== $node['afterCategoryId']) {
                $this->connection->update('blog_category', ['after_category_id' => $node['afterCategoryId'] === null ? null : Uuid::fromHexToBytes($node['afterCategoryId'])], ['id' => Uuid::fromHexToBytes($id)]);
            }
            if ($node['parentId'] === null) {
                $roots[] = $id;
            }
        }
        $this->treeUpdater->batchUpdate($roots, BlogCategoryEntity::ENTITY_NAME, $event->getContext(), true);
        $this->childCountUpdater->update(BlogCategoryEntity::ENTITY_NAME, array_keys($graph), $event->getContext());
    }

    private function lock(): void
    {
        // A permanent row serializes even the first insert and remains locked until the outer transaction commits.
        $this->connection->fetchOne('SELECT id FROM blog_category_tree_lock WHERE id = 1 FOR UPDATE');
    }

    /**
     * @return array<string, array{parentId: ?string, afterCategoryId: ?string}>
     */
    private function loadGraph(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) AS id, LOWER(HEX(parent_id)) AS parentId, LOWER(HEX(after_category_id)) AS afterCategoryId FROM blog_category ORDER BY id FOR UPDATE');
        $graph = [];
        foreach ($rows as $row) {
            $graph[$row['id']] = ['parentId' => $row['parentId'], 'afterCategoryId' => $row['afterCategoryId']];
        }

        return $graph;
    }

    /**
     * @param array<string, array{parentId: ?string, afterCategoryId: ?string}> $graph
     *
     * @return array<string, list<string>>
     */
    private function orderedGroups(array $graph): array
    {
        $groups = [];
        foreach ($graph as $id => $node) {
            $category = new BlogCategoryEntity();
            $category->id = $id;
            $category->afterCategoryId = $node['afterCategoryId'];
            $groups[$node['parentId'] ?? ''][] = $category;
        }

        return array_map(static fn (array $siblings): array => array_keys(AfterSort::sort($siblings, 'afterCategoryId')), $groups);
    }
}
