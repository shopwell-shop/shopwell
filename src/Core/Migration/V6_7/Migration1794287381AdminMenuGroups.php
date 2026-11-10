<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class Migration1794287381AdminMenuGroups extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794287381;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'admin_menu_item', 'route_key', 'VARCHAR(255)');
        $connection->transactional(function (Connection $connection): void {
            $rows = $connection->fetchAllAssociative('SELECT item.*, parent.internal_key AS parent_key, plugin.name AS plugin_name FROM admin_menu_item item LEFT JOIN admin_menu_item parent ON parent.id = item.parent_id LEFT JOIN plugin ON plugin.id = item.plugin_id ORDER BY item.created_at, item.id');
            $levels = [];
            foreach ($rows as $row) {
                $levels[$row['parent_id'] ?? ''][] = $row;
            }
            foreach ($levels as $siblings) {
                if (!array_filter($siblings, static fn (array $row): bool => $row['group_name'] !== null && $row['group_name'] !== '')) {
                    continue;
                }
                $this->convertLevel($connection, $this->order($siblings));
            }
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function convertLevel(Connection $connection, array $rows): void
    {
        $groups = [];
        $previous = null;
        $date = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        foreach ($rows as $row) {
            $group = $row['group_name'];
            if (!$group || $row['type'] === 'group') {
                $connection->update('admin_menu_item', ['after_menu_item_id' => $previous], ['id' => $row['id']]);
                $previous = $row['id'];
                continue;
            }
            // Stable identity shared with legacy provider normalization; retain this algorithm in migrations.
            $parentKey = $row['parent_key'] ?? ($row['parent_id'] === null ? null : Uuid::fromBytesToHex($row['parent_id']));
            $key = 'admin-menu-group:' . Hasher::hash(json_encode([$parentKey, $group, $row['plugin_name']], \JSON_THROW_ON_ERROR), 'sha256');
            $id = Uuid::fromHexToBytes(Uuid::fromStringToHex('admin-menu:' . $key));
            if (!isset($groups[$key])) {
                $connection->insert('admin_menu_item', [
                    'id' => $id,
                    'parent_id' => $row['parent_id'],
                    'after_menu_item_id' => $previous,
                    'internal_key' => $key,
                    'type' => 'group',
                    'plugin_id' => $row['plugin_id'],
                    'config_overridden' => 0,
                    'created_at' => $date,
                ]);
                $groups[$key] = ['id' => $id, 'previous' => null, 'overridden' => false, 'title' => \in_array($group, ['general', 'system', 'localization', 'content', 'customer', 'automation', 'commerce', 'account', 'plugins', 'payment'], true) ? 'sw-system.menu.groups.' . $group : $group];
                $previous = $id;
            }
            $groups[$key]['overridden'] = $groups[$key]['overridden'] || (bool) $row['config_overridden'];
            $connection->executeStatement(
                'INSERT IGNORE INTO admin_menu_item_translation (admin_menu_item_id, language_id, title, created_at) SELECT :groupId, language_id, group_title, :date FROM admin_menu_item_translation WHERE admin_menu_item_id = :itemId AND group_title IS NOT NULL AND group_title <> :empty',
                ['groupId' => $id, 'date' => $date, 'itemId' => $row['id'], 'empty' => '']
            );
            $connection->update('admin_menu_item', [
                'parent_id' => $id,
                'after_menu_item_id' => $groups[$key]['previous'],
                'group_name' => null,
            ], ['id' => $row['id']]);
            $groups[$key]['previous'] = $row['id'];
        }
        foreach ($groups as $group) {
            $connection->update('admin_menu_item', ['config_overridden' => (int) $group['overridden']], ['id' => $group['id']]);
            $connection->executeStatement(
                'INSERT IGNORE INTO admin_menu_item_translation (admin_menu_item_id, language_id, title, created_at) SELECT :id, id, :title, :date FROM language',
                ['id' => $group['id'], 'title' => $group['title'], 'date' => $date]
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function order(array $rows): array
    {
        $byId = array_column($rows, null, 'id');
        $ordered = [];
        $visited = [];
        $visit = function (array $row) use (&$visit, $byId, &$ordered, &$visited): void {
            if (isset($visited[$row['id']])) {
                return;
            }
            $visited[$row['id']] = true;
            if (isset($byId[$row['after_menu_item_id'] ?? ''])) {
                $visit($byId[$row['after_menu_item_id']]);
            }
            $ordered[] = $row;
        };
        foreach ($rows as $row) {
            $visit($row);
        }

        return $ordered;
    }
}
