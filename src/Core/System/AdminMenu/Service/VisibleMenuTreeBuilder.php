<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Service;

use Shopwell\Core\Framework\Api\Acl\AclRoleLoader;
use Shopwell\Core\Framework\Api\Acl\AclRoles;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;

/**
 * Builds the navigation tree a single administration user sees.
 *
 * The builder is the read side of the database driven navigation: it loads every active
 * menu item, drops what the current user must not see, renders the remaining rows as a
 * nested tree ordered by the `after_menu_item_id` chain, and fills the gaps with the
 * core declarations - a menu item the database does not know yet (typically a menu added
 * by a core upgrade) renders from its declaration instead of staying invisible.
 *
 * Two filters protect against data that lost its owner:
 *
 * - Rows of a plugin that is deactivated, missing or whose uninstall event was lost are
 *   filtered here, in addition to the lifecycle sync that removes or archives them.
 * - Rows pointing at an internal key no provider declares are filtered as well, so a
 *   renamed route degrades to a hidden entry instead of a dead link.
 */
#[Package('fundamentals@framework')]
class VisibleMenuTreeBuilder
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminMenuItemCollection> $menuItemRepository
     * @param EntityRepository<AclRoleCollection> $roleRepository
     */
    public function __construct(
        private readonly EntityRepository $menuItemRepository,
        private readonly AdminMenuRouteRegistry $routeRegistry,
        private readonly AclRoleLoader $aclRoleLoader,
        private readonly EntityRepository $roleRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(Context $context): array
    {
        return $this->buildNavigation($context)['menu'];
    }

    /**
     * @return array{menu: list<array<string, mixed>>, allowUnmanaged: bool}
     */
    public function buildNavigation(Context $context): array
    {
        $items = $this->menuItemRepository
            ->search($this->createCriteria(), $context)
            ->getEntities();

        $roles = $this->resolveRoles($context);

        // The keys of every stored row, also of the ones filtered out below: a customized
        // entry that is currently hidden must not be re-synthesized from its declaration.
        $storedKeys = [];
        // internal key => id of the row that carries it, also for hidden rows, so a
        // synthesized entry can anchor to its parent row instead of floating to the root
        $nodeKeyByInternalKey = [];

        foreach ($items as $item) {
            if ($item->getInternalKey() !== null) {
                $storedKeys[$item->getInternalKey()] = true;
                $nodeKeyByInternalKey[$item->getInternalKey()] = $item->getId();
            }
        }

        $nodes = [];
        foreach ($items as $item) {
            if (!$this->isVisible($item)) {
                continue;
            }

            $groupDefinition = $item->getType() === AdminMenuItemDefinition::TYPE_GROUP && $item->getInternalKey() !== null
                ? $this->routeRegistry->get($item->getInternalKey())
                : null;

            $nodes[$item->getId()] = [
                'id' => $item->getId(),
                'parentId' => $item->getParentId(),
                'afterMenuItemId' => $item->getAfterMenuItemId(),
                'internalKey' => $item->getInternalKey(),
                'routeKey' => $item->getRouteKey(),
                'type' => $item->getType(),
                'title' => $item->getTitle() ?? $groupDefinition?->title,
                'groupName' => $item->getGroupName() ?? $groupDefinition?->groupName,
                'groupTitle' => $item->getGroupTitle(),
                'icon' => $item->getIcon(),
                'color' => $item->getColor(),
                'linkTarget' => $item->getLinkTarget(),
                'privilege' => $item->getPrivilege(),
            ];
        }

        // Entries the database does not know yet render from their core declaration, so a
        // core upgrade cannot lose a newly added menu item to a one-time seed migration.
        $this->synthesizeMissingCoreItems(
            $this->routeRegistry->getCoreDefinitions(),
            $storedKeys,
            $nodeKeyByInternalKey,
            $nodes
        );

        return $this->filterForRoles($this->renderLevel(null, $nodes), $roles, $context);
    }

    /**
     * Every row, including the inactive and archived ones: the ones that are filtered out
     * below still have to be known, or the declarations they were seeded from would
     * synthesize them back into the tree and a hidden entry would never stay hidden.
     */
    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addAssociation('plugin');

        return $criteria;
    }

    private function isVisible(AdminMenuItemEntity $item): bool
    {
        if (!$item->isActive() || $item->isArchived()) {
            return false;
        }

        if ($item->getType() === AdminMenuItemDefinition::TYPE_HIDDEN) {
            return false;
        }

        if ($item->getPluginId() !== null) {
            $plugin = $item->getPlugin();

            if ($plugin === null || !$plugin->getActive()) {
                return false;
            }
        }

        if ($item->getType() === AdminMenuItemDefinition::TYPE_MENU) {
            $internalKey = $item->getRouteKey() ?? $item->getInternalKey();

            if ($internalKey === null || $this->routeRegistry->get($internalKey)?->type !== AdminMenuItemDefinition::TYPE_MENU) {
                return false;
            }
        }

        return true;
    }

    /**
     * Menu selection only narrows navigation. Page and API ACL remain authoritative.
     *
     * @param list<array<string, mixed>> $tree
     *
     * @return array{menu: list<array<string, mixed>>, allowUnmanaged: bool}
     */
    private function filterForRoles(array $tree, AclRoles $roles, Context $context): array
    {
        if ($roles->unrestricted) {
            return ['menu' => $tree, 'allowUnmanaged' => true];
        }
        if ($roles->roleIds === []) {
            return ['menu' => [], 'allowUnmanaged' => false];
        }
        $criteria = new Criteria($roles->roleIds);
        $criteria->addAssociation('adminMenuItems');
        $selections = [];
        foreach ($this->roleRepository->search($criteria, $context)->getEntities() as $role) {
            foreach ($role->getAdminMenuItems()?->getIds() ?? [] as $id) {
                $selections[$id] = true;
            }
        }

        return ['menu' => $this->filterSelection($tree, $selections), 'allowUnmanaged' => false];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, true> $selection
     *
     * @return list<array<string, mixed>>
     */
    private function filterSelection(array $nodes, array $selection): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $node['children'] = $this->filterSelection($node['children'], $selection);
            $container = \in_array($node['type'], ['folder', 'group'], true);
            if ($node['children'] !== [] || (!$container && isset($selection[$node['id']]))) {
                $result[] = $node;
            }
        }

        return $result;
    }

    private function resolveRoles(Context $context): AclRoles
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            // System and other non-user sources see the full tree; the route ACL still
            // guards every page behind it.
            return new AclRoles(true, []);
        }

        $userId = $source->getUserId();

        if ($userId === null || $source->isAdmin()) {
            return new AclRoles(true, []);
        }

        return $this->aclRoleLoader->getUserRoles([$userId])[$userId] ?? new AclRoles(false, []);
    }

    /**
     * Adds a node for every core declaration without a stored row, anchored below its
     * declared parent - either the parent's row, or the parent's own synthesized node.
     *
     * A synthesized entry whose parent row exists but is not renderable (a hidden or
     * archived parent) is dropped with the subtree orphan cleanup, the same way stored
     * children of a hidden parent are.
     *
     * @param list<AdminMenuDefinitionStruct> $coreDefinitions
     * @param array<string, true> $storedKeys
     * @param array<string, string> $nodeKeyByInternalKey
     * @param array<string, array<string, mixed>> $nodes
     */
    private function synthesizeMissingCoreItems(
        array $coreDefinitions,
        array $storedKeys,
        array $nodeKeyByInternalKey,
        array &$nodes
    ): void {
        $missingByInternalKey = [];

        foreach ($coreDefinitions as $definition) {
            if (isset($storedKeys[$definition->internalKey])) {
                continue;
            }

            $missingByInternalKey[$definition->internalKey] = true;

            $nodes['virtual:' . $definition->internalKey] = [
                'id' => 'virtual:' . $definition->internalKey,
                'parentId' => null,
                'afterMenuItemId' => null,
                'internalKey' => $definition->internalKey,
                'type' => $definition->type,
                'title' => $definition->title,
                'groupName' => $definition->groupName,
                'groupTitle' => null,
                'icon' => $definition->icon,
                'color' => $definition->color,
                'linkTarget' => $definition->linkTarget,
                'privilege' => $definition->privilege,
            ];
        }

        foreach ($missingByInternalKey as $internalKey => $_) {
            $nodeKey = 'virtual:' . $internalKey;
            $parentInternalKey = null;

            foreach ($coreDefinitions as $definition) {
                if ($definition->internalKey === $internalKey) {
                    $parentInternalKey = $definition->parent;

                    break;
                }
            }

            if ($parentInternalKey === null) {
                continue;
            }

            // A stored parent row wins, even when it is not renderable: the child is
            // then dropped by the orphan cleanup instead of floating to the root.
            $nodes[$nodeKey]['parentId'] = $nodeKeyByInternalKey[$parentInternalKey]
                ?? (isset($missingByInternalKey[$parentInternalKey]) ? 'virtual:' . $parentInternalKey : null);
        }
    }

    /**
     * Renders one level of the tree: the nodes anchored below `$parentKey`, ordered by
     * their `after_menu_item_id` chain, each with its rendered children nested.
     *
     * Nodes whose parent is missing are not rendered - they are unreachable from the
     * root, so no orphan can appear in the navigation.
     *
     * @param array<string, array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function renderLevel(?string $parentKey, array $nodes): array
    {
        $levelKeys = [];

        foreach ($nodes as $nodeKey => $node) {
            if ($node['parentId'] === $parentKey) {
                $levelKeys[] = $nodeKey;
            }
        }

        $level = [];

        foreach ($this->sortChain($levelKeys, $nodes) as $nodeKey) {
            $node = $nodes[$nodeKey];
            $node['children'] = $this->renderLevel($nodeKey, $nodes);

            if ($node['type'] !== AdminMenuItemDefinition::TYPE_GROUP || $node['children'] !== []) {
                $level[] = $node;
            }
        }

        return $level;
    }

    /**
     * Orders sibling node keys by following the `after_menu_item_id` chain: the entry
     * without a predecessor leads, every other entry follows the sibling it is
     * positioned after.
     *
     * Data written before a chain was fixed up may be broken: an entry can reference a
     * sibling that is not part of the level, several entries can share a predecessor.
     * Unreachable entries keep their insertion order instead of disappearing, and a
     * cycle is broken at the first repeated entry instead of looping forever.
     *
     * @param list<string> $nodeKeys
     * @param array<string, array<string, mixed>> $nodes
     *
     * @return list<string>
     */
    private function sortChain(array $nodeKeys, array $nodes): array
    {
        $inLevel = array_fill_keys($nodeKeys, true);
        $followers = [];
        $heads = [];

        foreach ($nodeKeys as $nodeKey) {
            $after = $nodes[$nodeKey]['afterMenuItemId'] ?? null;

            if ($after === null || !isset($inLevel[$after])) {
                $heads[] = $nodeKey;

                continue;
            }

            $followers[$after][] = $nodeKey;
        }

        $ordered = [];
        $visited = [];

        foreach ($heads as $head) {
            $cursor = $head;

            while ($cursor !== null && !isset($visited[$cursor])) {
                $visited[$cursor] = true;
                $ordered[] = $cursor;

                $cursor = $followers[$cursor][0] ?? null;
            }
        }

        foreach ($nodeKeys as $nodeKey) {
            if (!isset($visited[$nodeKey])) {
                $ordered[] = $nodeKey;
            }
        }

        return $ordered;
    }
}
