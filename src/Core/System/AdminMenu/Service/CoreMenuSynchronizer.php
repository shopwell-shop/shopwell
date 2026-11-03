<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\CoreMenuRow;

/**
 * Writes and repairs the navigation rows so they match the core declarations.
 *
 * There is one implementation for both jobs on purpose. The rows are part of an
 * installation, so `system:install` runs `bin/console admin-menu:sync`; the same command
 * repairs them afterwards - after an upgrade that relabelled an entry, after a restored
 * backup, after a table someone cleaned up. A migration could seed them too, but it would
 * have to write the rows as raw SQL and repeat every rule below (locking, customization,
 * ordering) a second time.
 *
 * What it does:
 *
 * - A declaration without a row is created, locked (`sys_locked`) and ordered into the
 *   `after_menu_item_id` chain of its level. Locked means the entry may be renamed, hidden
 *   or moved, but never deleted - it is part of the product.
 * - A row whose declaration fields changed is refreshed, so a relabelled or re-iconed entry
 *   follows the release.
 * - A row whose internal key no provider declares anymore is deleted. A core upgrade that
 *   renames an entry must not leave its row behind as a ghost: the visible tree filters it,
 *   but the management page would carry it as garbage forever. The declaration decides
 *   whether a core row exists at all, so it also decides when it stops existing - this
 *   includes a row the administrator once customized. Rows a plugin synced are the plugin
 *   lifecycle's business and rows an administrator created (no internal key) are kept.
 * - A row an administrator customized is never touched. The declaration only decides whether
 *   the row exists at all.
 *
 * Steady state writes nothing: when every row already carries its declaration fields and its
 * place, the method returns after a single read.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class CoreMenuSynchronizer
{
    /**
     * The service is shared, so one process synchronizes at most once.
     */
    private bool $synchronized = false;

    /**
     * @param EntityRepository<AdminMenuItemCollection> $menuItemRepository
     */
    public function __construct(
        private readonly EntityRepository $menuItemRepository,
        private readonly AdminMenuRouteRegistry $routeRegistry,
        private readonly CoreMenuRowsBuilder $rowsBuilder,
    ) {
    }

    /**
     * @return int the number of rows written or removed, 0 when the table already matches the declarations
     */
    public function synchronize(): int
    {
        if ($this->synchronized) {
            return 0;
        }

        $declarations = $this->routeRegistry->getCoreDefinitions();

        if ($declarations === []) {
            return 0;
        }

        $context = $this->createContext();
        $stored = $this->storedItems($context);

        $declaredKeys = [];
        foreach ($declarations as $declaration) {
            $declaredKeys[$declaration->internalKey] = true;
        }

        $ghosts = $this->undeclaredRows($stored, $declaredKeys);

        foreach (array_keys($ghosts) as $internalKey) {
            unset($stored[$internalKey]);
        }

        // a customized row keeps the place it was given, but the entry that follows it still
        // has to be chained behind it - so it is passed in as an anchor instead of a row
        $anchors = [];

        foreach ($stored as $internalKey => $item) {
            if ($item->isConfigOverridden()) {
                $anchors[$internalKey] = $item->getId();
            }
        }

        $writes = [];
        $placements = [];

        foreach ($this->rowsBuilder->build($declarations, $anchors) as $row) {
            $existing = $stored[$row->internalKey] ?? null;

            if ($existing === null) {
                $writes[] = $this->creationPayload($row);

                continue;
            }

            if (!$this->matchesDeclaration($existing, $row)) {
                $writes[] = $this->refreshPayload($row);
            }

            if (!$this->isPlaced($existing, $row)) {
                $placements[] = $row->placement();
            }
        }

        if ($writes !== []) {
            $this->menuItemRepository->upsert($writes, $context);
        }

        if ($placements !== []) {
            $this->menuItemRepository->update($placements, $context);
        }

        // last, because the delete cascades: every row that has to survive has been moved
        // and placed above already
        $this->removeRows($ghosts, array_column($placements, 'id'), $context);

        $this->synchronized = true;

        return \count($writes) + \count($ghosts);
    }

    /**
     * Collects the core rows whose internal key no declaration carries anymore.
     *
     * @param array<string, AdminMenuItemEntity> $stored indexed by internal key
     * @param array<string, true> $declaredKeys
     *
     * @return array<string, string> internal key to id
     */
    private function undeclaredRows(array $stored, array $declaredKeys): array
    {
        $ghosts = [];

        foreach ($stored as $internalKey => $item) {
            // rows a plugin synced are removed by the plugin lifecycle, rows an administrator
            // created carry no internal key and are never a core row
            if ($item->getPluginId() !== null || $item->getInternalKey() === null) {
                continue;
            }

            if (!isset($declaredKeys[$internalKey])) {
                $ghosts[$internalKey] = $item->getId();
            }
        }

        return $ghosts;
    }

    /**
     * Deletes the rows a core upgrade left behind.
     *
     * A delete cascades to the children (`fk.admin_menu_item.parent_id`), so a child that has
     * to survive is moved to the top level first: a row an administrator created has no
     * declaration and no internal key, it lives only in the table and would silently go down
     * with its parent. A declared child is not moved here - the placement pass above already
     * re-parented it, which is why the ids it wrote are skipped.
     *
     * @param array<string, string> $ghosts internal key to id of the rows to delete
     * @param list<string> $placed ids the placement pass wrote in this run
     */
    private function removeRows(array $ghosts, array $placed, Context $context): void
    {
        if ($ghosts === []) {
            return;
        }

        $ghostIds = array_values($ghosts);

        $children = $this->menuItemRepository
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsAnyFilter('parentId', $ghostIds))
                    ->setLimit(null),
                $context
            )
            ->getIds();

        $survivors = array_values(array_diff($children, $placed));

        if ($survivors !== []) {
            $this->menuItemRepository->update(
                array_map(static fn (string $id): array => [
                    'id' => $id,
                    'parentId' => null,
                    'afterMenuItemId' => null,
                ], $survivors),
                $context
            );
        }

        $this->menuItemRepository->delete(
            array_map(static fn (string $id): array => ['id' => $id], $ghostIds),
            $context
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function creationPayload(CoreMenuRow $row): array
    {
        return [
            ...$row->toArray(),
            // a core entry is part of the product: rename it, hide it, move it - but a
            // misbehaving client must not be able to delete the navigation
            'sysLocked' => true,
            'active' => true,
            'archived' => false,
            'configOverridden' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshPayload(CoreMenuRow $row): array
    {
        return [
            ...$row->declarationFields(),
            'id' => $row->id,
            'defaultMeta' => $row->defaultMeta,
        ];
    }

    /**
     * Whether the row already carries what the declaration says.
     *
     * The fields are compared one by one instead of comparing against the stored
     * `default_meta`: MySQL reorders the keys of a JSON document, so a decoded snapshot is
     * never identical to the encoded one and a `default_meta` comparison would report a
     * change on every run.
     */
    private function matchesDeclaration(AdminMenuItemEntity $item, CoreMenuRow $row): bool
    {
        // the label is deliberately not compared: it belongs to the translation table and to
        // whoever wrote it there, a declaration only supplies the key the page falls back to
        return $item->getType() === $row->type
            && $item->getGroupName() === $row->groupName
            && $item->getIcon() === $row->icon
            && $item->getColor() === $row->color
            && $item->getPrivilege() === $row->privilege
            && $item->getLinkTarget() === $row->linkTarget;
    }

    private function isPlaced(AdminMenuItemEntity $item, CoreMenuRow $row): bool
    {
        return $item->getParentId() === $row->parentId
            && $item->getAfterMenuItemId() === $row->afterMenuItemId;
    }

    /**
     * @return array<string, AdminMenuItemEntity> indexed by internal key
     */
    private function storedItems(Context $context): array
    {
        $items = $this->menuItemRepository
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('pluginId', null))
                    ->setLimit(null),
                $context
            )
            ->getEntities();

        $indexed = [];

        foreach ($items as $item) {
            $internalKey = $item->getInternalKey();

            if ($internalKey !== null) {
                $indexed[$internalKey] = $item;
            }
        }

        return $indexed;
    }

    /**
     * Marks the writes as declaration writes, the same way the plugin synchronization
     * does: {@see \Shopwell\Core\System\AdminMenu\AdminMenuValidator} treats them as system
     * writes and neither flags the rows as administrator-customized nor rejects the internal keys.
     */
    private function createContext(): Context
    {
        $context = Context::createDefaultContext();
        $context->addExtension(PluginMenuSynchronizer::CONTEXT_EXTENSION, new ArrayStruct());

        return $context;
    }
}
