<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794287381AdminMenuGroups;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(Migration1794287381AdminMenuGroups::class)]
class Migration1794287381AdminMenuGroupsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = KernelLifecycleManager::getConnection();
        // Schema DDL must finish before the fixture transaction (MySQL commits DDL implicitly).
        (new Migration1794287381AdminMenuGroups())->update($connection);
        $connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        KernelLifecycleManager::getConnection()->rollBack();
        parent::tearDown();
    }

    public function testMovesLegacyGroupsOnceAndPreservesTranslationsAndOrder(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $parent = Uuid::randomBytes();
        $otherParent = Uuid::randomBytes();
        $first = Uuid::randomBytes();
        $second = Uuid::randomBytes();
        $other = Uuid::randomBytes();
        $date = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        foreach ([$parent, $otherParent] as $id) {
            $connection->insert('admin_menu_item', ['id' => $id, 'type' => 'folder', 'created_at' => $date]);
        }
        foreach ([[$first, $parent, null], [$second, $parent, $first], [$other, $otherParent, null]] as [$id, $parentId, $after]) {
            $connection->insert('admin_menu_item', ['id' => $id, 'type' => 'menu', 'parent_id' => $parentId, 'after_menu_item_id' => $after, 'group_name' => 'custom-group', 'config_overridden' => 1, 'created_at' => $date]);
        }
        $connection->insert('admin_menu_item_translation', ['admin_menu_item_id' => $first, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'title' => 'Page', 'group_title' => 'My tools', 'created_at' => $date]);

        $migration = new Migration1794287381AdminMenuGroups();
        $migration->update($connection);
        $groupId = $connection->fetchOne('SELECT parent_id FROM admin_menu_item WHERE id = ?', [$first]);
        static::assertIsString($groupId);
        static::assertSame('group', $connection->fetchOne('SELECT type FROM admin_menu_item WHERE id = ?', [$groupId]));
        static::assertSame($parent, $connection->fetchOne('SELECT parent_id FROM admin_menu_item WHERE id = ?', [$groupId]));
        static::assertSame($groupId, $connection->fetchOne('SELECT parent_id FROM admin_menu_item WHERE id = ?', [$second]));
        static::assertSame($first, $connection->fetchOne('SELECT after_menu_item_id FROM admin_menu_item WHERE id = ?', [$second]));
        static::assertNotSame($groupId, $connection->fetchOne('SELECT parent_id FROM admin_menu_item WHERE id = ?', [$other]));
        static::assertSame('My tools', $connection->fetchOne('SELECT title FROM admin_menu_item_translation WHERE admin_menu_item_id = ? AND language_id = ?', [$groupId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
        static::assertSame('Page', $connection->fetchOne('SELECT title FROM admin_menu_item_translation WHERE admin_menu_item_id = ?', [$first]));
        $before = $connection->fetchAllAssociative('SELECT * FROM admin_menu_item WHERE id IN (?, ?, ?)', [$first, $second, $groupId]);
        $migration->update($connection);
        static::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM admin_menu_item WHERE id IN (?, ?, ?)', [$first, $second, $groupId]));
    }

    public function testUntouchedDefaultsRemainEligibleForProviderUpdates(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $id = Uuid::randomBytes();
        $date = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $connection->insert('admin_menu_item', ['id' => $id, 'internal_key' => 'untouched-page', 'type' => 'menu', 'group_name' => 'general', 'config_overridden' => 0, 'created_at' => $date]);
        (new Migration1794287381AdminMenuGroups())->update($connection);
        $row = $connection->fetchAssociative('SELECT config_overridden, parent_id FROM admin_menu_item WHERE id = ?', [$id]);
        static::assertIsArray($row);
        static::assertSame(0, (int) $row['config_overridden']);
        static::assertSame(0, (int) $connection->fetchOne('SELECT config_overridden FROM admin_menu_item WHERE id = ?', [$row['parent_id']]));
    }
}
