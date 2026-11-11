<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794296487RoleMenuAccess;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(Migration1794296487RoleMenuAccess::class)]
class Migration1794296487RoleMenuAccessTest extends TestCase
{
    public function testAddsAutomaticNavigationModeIdempotently(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('ALTER TABLE acl_role DROP COLUMN menu_access_mode');
        $migration = new Migration1794296487RoleMenuAccess();
        $migration->update($connection);
        $migration->update($connection);

        $columns = $connection->createSchemaManager()->introspectTableColumnsByUnquotedName('acl_role');
        $column = array_find($columns, static fn ($column): bool => $column->getObjectName()->getIdentifier()->getValue() === 'menu_access_mode');
        static::assertNotNull($column);
        static::assertSame('auto', $column->getDefault());
        static::assertTrue($column->getNotnull());
    }

    public function testSplitsNamedPrivilegesAndRenamesMenuTargetsWithoutChangingIdentity(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->beginTransaction();
        try {
            $roleId = Uuid::randomBytes();
            $menuId = Uuid::randomBytes();
            $connection->insert('acl_role', [
                'id' => $roleId, 'name' => 'Split role',
                'privileges' => json_encode(['users_and_permissions.editor', 'product.viewer'], \JSON_THROW_ON_ERROR),
                'created_at' => '2026-11-10 00:00:00',
            ]);
            $connection->insert('admin_menu_item', [
                'id' => $menuId, 'type' => 'menu', 'route_key' => 'sw-system-user', 'active' => 1,
                'created_at' => '2026-11-10 00:00:00',
            ]);
            $migration = new Migration1794296487RoleMenuAccess();
            $migration->update($connection);
            $migration->update($connection);
            $privileges = json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$roleId]), true, flags: \JSON_THROW_ON_ERROR);
            static::assertContains('user.editor', $privileges);
            static::assertContains('organization_unit:read', $privileges);
            static::assertContains('position:read', $privileges);
            static::assertContains('user_organization_unit:read', $privileges);
            static::assertContains('user_organization_unit:update', $privileges);
            static::assertNotContains('organization_unit:update', $privileges);
            static::assertNotContains('position:update', $privileges);
            static::assertContains('permissions.editor', $privileges);
            static::assertContains('product.viewer', $privileges);
            static::assertContains('admin_menu_item_role:create', $privileges);
            static::assertNotContains('users_and_permissions.editor', $privileges);
            static::assertSame('sw-user', $connection->fetchOne('SELECT route_key FROM admin_menu_item WHERE id = ?', [$menuId]));
        } finally {
            $connection->rollBack();
        }
    }
}
