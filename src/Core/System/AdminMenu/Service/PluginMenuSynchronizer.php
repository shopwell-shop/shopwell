<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;

/**
 * Materializes the navigation declarations of a plugin into the item table, following the
 * plugin lifecycle. It is the write side of {@link AdminMenuDefinitionProviderInterface}:
 * a plugin declares, the synchronizer owns the rows.
 *
 * The rules it keeps:
 *
 * - An item is identified by `plugin_id + internal_key`, so installing, updating and
 *   reinstalling a plugin can never duplicate a row.
 * - A declaration never overwrites what an administrator customized. Only items the
 *   administrator left alone are refreshed, so an update can add labels and icons
 *   without throwing away an order or a label somebody deliberately changed.
 * - An item is only rendered while its plugin is active. The lifecycle therefore flips
 *   `active` and never deletes on deactivation.
 * - `position` is a declaration-time value; it is materialized into the
 *   `after_menu_item_id` chain of the level, which is the only ordering the database and
 *   the administration tree know about.
 */
#[Package('fundamentals@framework')]
class PluginMenuSynchronizer
{
    /**
     * Marks the writes of the synchronizer in the context, so the validator can tell them
     * apart from an administrator customizing an item.
     */
    final public const CONTEXT_EXTENSION = 'admin-menu-synchronization';

    /**
     * @internal
     *
     * @param EntityRepository<AdminMenuItemCollection> $menuItemRepository
     */
    public function __construct(
        private readonly EntityRepository $menuItemRepository,
        private readonly AdminMenuRouteRegistry $routeRegistry,
    ) {
    }

    /**
     * The plugin is installed, but not activated yet, so its items are seeded invisible.
     * Reinstalling an archived plugin takes its rows back instead of creating new ones.
     */
    public function syncInstall(PluginEntity $plugin): void
    {
        $this->synchronize($plugin, false);
    }

    /**
     * Activation also seeds: an install that ran without the declarations being loaded -
     * the container of that request does not know the freshly installed plugin yet -
     * heals on the next activation instead of leaving the navigation empty.
     */
    public function syncActivation(PluginEntity $plugin): void
    {
        $this->synchronize($plugin, true);
    }

    /**
     * A deactivated plugin keeps its rows, its role bindings and the customizations, so
     * reactivating it restores the exact navigation it had.
     */
    public function syncDeactivation(PluginEntity $plugin): void
    {
        $context = $this->createContext();

        $updates = [];

        foreach ($this->storedItems($plugin, $context) as $item) {
            $updates[] = ['id' => $item->getId(), 'active' => false];
        }

        if ($updates !== []) {
            $this->menuItemRepository->update($updates, $context);
        }
    }

    /**
     * An update adds the declarations a release added, refreshes the ones the
     * administrator did not touch and hides the ones the release dropped.
     */
    public function syncUpdate(PluginEntity $plugin): void
    {
        $this->synchronize($plugin, $plugin->getActive());
    }

    /**
     * Uninstalling either keeps the rows for a later reinstall or removes them together
     * with their translations and role bindings.
     */
    public function syncUninstall(PluginEntity $plugin, bool $keepUserData): void
    {
        if ($keepUserData) {
            $this->archive($plugin);

            return;
        }

        $this->purge($plugin);
    }

    private function synchronize(PluginEntity $plugin, bool $active): void
    {
        $context = $this->createContext();
        $declarations = $this->routeRegistry->getPluginDefinitions($plugin->getName());
        $stored = $this->storedItems($plugin, $context);

        $upserts = [];
        $ids = [];

        foreach ($declarations as $declaration) {
            $existing = $stored[$declaration->internalKey] ?? null;

            // a pre-assigned id keeps the chain linking below independent of the write result
            $ids[$declaration->internalKey] = $existing?->getId() ?? Uuid::randomHex();

            $upserts[] = $this->buildPayload($declaration, $plugin, $ids[$declaration->internalKey], $active, $existing);
        }

        if ($upserts !== []) {
            $this->menuItemRepository->upsert($upserts, $context);
        }

        $this->linkLevels($declarations, $ids, $stored, $context);
        $this->hideDroppedDeclarations($stored, $declarations, $active, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        AdminMenuDefinitionStruct $declaration,
        PluginEntity $plugin,
        string $id,
        bool $active,
        ?AdminMenuItemEntity $existing,
    ): array {
        $payload = [
            'id' => $id,
            'pluginId' => $plugin->getId(),
            'internalKey' => $declaration->internalKey,
            'active' => $active,
            // reinstalling a plugin that kept its user data takes the archived rows back
            'archived' => false,
            'defaultMeta' => $declaration->jsonSerialize(),
        ];

        if ($existing !== null && $existing->isConfigOverridden()) {
            // the administrator owns this item, the declaration only decides its visibility
            return $payload;
        }

        return [
            ...$payload,
            'type' => $declaration->type,
            'title' => $declaration->title,
            'icon' => $declaration->icon,
            'color' => $declaration->color,
            'privilege' => $declaration->privilege,
            'linkTarget' => $declaration->linkTarget,
        ];
    }

    /**
     * Materializes `position` into the `after_menu_item_id` chain, level by level. A level
     * is the set of declarations sharing a declared parent, ordered by position; the first
     * one leads the level.
     *
     * A customized item keeps the place the administrator gave it, and the declaration
     * that follows it becomes its follower, so the chain stays closed either way.
     *
     * @param list<AdminMenuDefinitionStruct> $declarations
     * @param array<string, string> $ids internal key to item id
     * @param array<string, AdminMenuItemEntity> $stored
     */
    private function linkLevels(array $declarations, array $ids, array $stored, Context $context): void
    {
        $levels = [];

        foreach ($declarations as $declaration) {
            $levels[$declaration->parent ?? ''][] = $declaration;
        }

        $updates = [];

        foreach ($levels as $parentKey => $siblings) {
            $predecessor = null;

            foreach ($this->sortByPosition($siblings) as $declaration) {
                $customized = $stored[$declaration->internalKey] ?? null;

                if ($customized !== null && $customized->isConfigOverridden()) {
                    $predecessor = $customized->getId();

                    continue;
                }

                $updates[] = [
                    'id' => $ids[$declaration->internalKey],
                    'parentId' => $parentKey === '' ? null : $this->resolveParentId($parentKey, $ids, $context),
                    'afterMenuItemId' => $predecessor,
                ];

                $predecessor = $ids[$declaration->internalKey];
            }
        }

        if ($updates !== []) {
            $this->menuItemRepository->update($updates, $context);
        }
    }

    /**
     * A declaration the plugin dropped in this release keeps its row - it may bind roles,
     * and it belongs to the administrator - but it stops rendering.
     *
     * @param array<string, AdminMenuItemEntity> $stored
     * @param list<AdminMenuDefinitionStruct> $declarations
     */
    private function hideDroppedDeclarations(array $stored, array $declarations, bool $active, Context $context): void
    {
        $declared = array_column($declarations, 'internalKey');

        $updates = [];

        foreach ($stored as $internalKey => $item) {
            if (!\in_array($internalKey, $declared, true) && $item->getType() === AdminMenuItemDefinition::TYPE_GROUP && $item->getDefaultMeta() === null && $item->isConfigOverridden()) {
                if ($item->isActive() !== $active || $item->isArchived()) {
                    $updates[] = ['id' => $item->getId(), 'active' => $active, 'archived' => false];
                }

                continue;
            }

            if (\in_array($internalKey, $declared, true) || !$item->isActive()) {
                continue;
            }

            $updates[] = ['id' => $item->getId(), 'active' => false];
        }

        if ($updates !== []) {
            $this->menuItemRepository->update($updates, $context);
        }
    }

    /**
     * The declared parent is an internal key, the row needs an id. A parent that is a core
     * entry may not have a row yet - the core navigation renders from its declarations
     * until somebody customizes it - so the top level core entries are seeded on demand.
     *
     * @param array<string, string> $ids
     */
    private function resolveParentId(string $parentKey, array $ids, Context $context): ?string
    {
        if (isset($ids[$parentKey])) {
            return $ids[$parentKey];
        }

        $existing = $this->menuItemRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('internalKey', $parentKey))
                ->addFilter(new EqualsFilter('pluginId', null)),
            $context
        )->firstId();

        if ($existing !== null) {
            return $existing;
        }

        $declaration = $this->routeRegistry->get($parentKey);

        if ($declaration === null || $declaration->parent !== null) {
            // a nested core entry is seeded with its own level when it is customized
            return null;
        }

        $id = Uuid::randomHex();

        $this->menuItemRepository->upsert([[
            'id' => $id,
            'internalKey' => $declaration->internalKey,
            'type' => $declaration->type,
            'title' => $declaration->title,
            'icon' => $declaration->icon,
            'color' => $declaration->color,
            'privilege' => $declaration->privilege,
            'linkTarget' => $declaration->linkTarget,
            'active' => true,
            'sysLocked' => true,
            'defaultMeta' => $declaration->jsonSerialize(),
        ]], $context);

        return $id;
    }

    /**
     * @param list<AdminMenuDefinitionStruct> $declarations
     *
     * @return list<AdminMenuDefinitionStruct>
     */
    private function sortByPosition(array $declarations): array
    {
        usort(
            $declarations,
            static fn (AdminMenuDefinitionStruct $a, AdminMenuDefinitionStruct $b): int => ($a->position ?? \PHP_INT_MAX) <=> ($b->position ?? \PHP_INT_MAX)
        );

        return $declarations;
    }

    private function archive(PluginEntity $plugin): void
    {
        $context = $this->createContext();

        $updates = [];

        foreach ($this->storedItems($plugin, $context) as $item) {
            $updates[] = ['id' => $item->getId(), 'archived' => true, 'active' => false];
        }

        if ($updates !== []) {
            $this->menuItemRepository->update($updates, $context);
        }
    }

    /**
     * The translations and the role bindings go with the items, their foreign keys cascade.
     */
    private function purge(PluginEntity $plugin): void
    {
        $context = $this->createContext();
        $ids = [];

        foreach ($this->storedItems($plugin, $context) as $item) {
            $ids[] = ['id' => $item->getId()];
        }

        if ($ids === []) {
            return;
        }

        $this->menuItemRepository->delete($ids, $context);
    }

    /**
     * @return array<string, AdminMenuItemEntity> indexed by internal key
     */
    private function storedItems(PluginEntity $plugin, Context $context): array
    {
        $items = $this->menuItemRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('pluginId', $plugin->getId()))
                ->setLimit(null),
            $context
        )->getEntities();

        $indexed = [];

        foreach ($items as $item) {
            $indexed[$item->getInternalKey() ?? $item->getId()] = $item;
        }

        return $indexed;
    }

    private function createContext(): Context
    {
        $context = Context::createDefaultContext();
        $context->addExtension(self::CONTEXT_EXTENSION, new ArrayStruct());

        return $context;
    }
}
