<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Keeps legacy provider groupName declarations compatible with independent group nodes.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
final class MenuGroupNormalizer
{
    public static function key(?string $parent, string $group, ?string $owner): string
    {
        return 'admin-menu-group:' . Hasher::hash(json_encode([$parent, $group, $owner], \JSON_THROW_ON_ERROR), 'sha256');
    }

    /**
     * @param list<AdminMenuDefinitionStruct> $definitions
     *
     * @return list<AdminMenuDefinitionStruct>
     */
    public static function normalize(array $definitions, ?string $owner): array
    {
        $groups = [];
        $normalized = [];

        foreach ($definitions as $definition) {
            if (!$definition->groupName || $definition->type === AdminMenuItemDefinition::TYPE_GROUP) {
                $normalized[] = $definition;

                continue;
            }

            $key = self::key($definition->parent, $definition->groupName, $owner);
            if (!isset($groups[$key])) {
                $groups[$key] = new AdminMenuDefinitionStruct(
                    internalKey: $key,
                    type: AdminMenuItemDefinition::TYPE_GROUP,
                    title: \in_array($definition->groupName, ['general', 'system', 'localization', 'content', 'customer', 'automation', 'commerce', 'account', 'plugins', 'payment'], true) ? 'sw-system.menu.groups.' . $definition->groupName : $definition->groupName,
                    parent: $definition->parent,
                    position: $definition->position,
                    groupName: $definition->groupName,
                );
            } elseif (($definition->position ?? \PHP_INT_MAX) < ($groups[$key]->position ?? \PHP_INT_MAX)) {
                $groups[$key]->position = $definition->position;
            }

            $entry = clone $definition;
            $entry->parent = $key;
            $entry->groupName = null;
            $normalized[] = $entry;
        }

        return [...array_values($groups), ...$normalized];
    }
}
