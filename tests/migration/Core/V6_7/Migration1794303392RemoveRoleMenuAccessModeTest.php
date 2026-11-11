<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794303392RemoveRoleMenuAccessMode;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(Migration1794303392RemoveRoleMenuAccessMode::class)]
class Migration1794303392RemoveRoleMenuAccessModeTest extends TestCase
{
    public function testRemovesModeIdempotentlyWithoutChangingPermissionsOrMenus(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $columns = $connection->createSchemaManager()->introspectTableColumnsByUnquotedName('acl_role');
        $column = array_find($columns, static fn ($column): bool => $column->getObjectName()->getIdentifier()->getValue() === 'menu_access_mode');
        if ($column === null) {
            $connection->executeStatement('ALTER TABLE acl_role ADD COLUMN menu_access_mode VARCHAR(16) NOT NULL DEFAULT \'auto\'');
        }
        $roleId = Uuid::randomBytes();
        $menuId = Uuid::randomBytes();
        $privileges = json_encode(['product.viewer'], \JSON_THROW_ON_ERROR);
        try {
            $connection->insert('acl_role', [
                'id' => $roleId, 'name' => 'Explicit menu selection', 'privileges' => $privileges,
                'menu_access_mode' => 'custom', 'created_at' => '2026-11-10 00:00:00',
            ]);
            $connection->insert('admin_menu_item', [
                'id' => $menuId, 'type' => 'link', 'active' => 1, 'link_target' => 'https://example.com',
                'created_at' => '2026-11-10 00:00:00',
            ]);
            $connection->insert('admin_menu_item_role', [
                'acl_role_id' => $roleId, 'admin_menu_item_id' => $menuId, 'created_at' => '2026-11-10 00:00:00',
            ]);
            $migration = new Migration1794303392RemoveRoleMenuAccessMode();

            $migration->updateDestructive($connection);
            $migration->updateDestructive($connection);

            $remaining = $connection->createSchemaManager()->introspectTableColumnsByUnquotedName('acl_role');
            static::assertNull(array_find($remaining, static fn ($column): bool => $column->getObjectName()->getIdentifier()->getValue() === 'menu_access_mode'));
            static::assertSame($privileges, $connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$roleId]));
            static::assertSame([$menuId], $connection->fetchFirstColumn('SELECT admin_menu_item_id FROM admin_menu_item_role WHERE acl_role_id = ?', [$roleId]));
        } finally {
            $connection->delete('acl_role', ['id' => $roleId]);
            $connection->delete('admin_menu_item', ['id' => $menuId]);
            $restored = $connection->createSchemaManager()->introspectTableColumnsByUnquotedName('acl_role');
            if ($column !== null && array_find($restored, static fn ($column): bool => $column->getObjectName()->getIdentifier()->getValue() === 'menu_access_mode') === null) {
                $connection->executeStatement('ALTER TABLE acl_role ADD COLUMN menu_access_mode VARCHAR(16) NOT NULL DEFAULT \'auto\'');
            }
        }
    }
}
