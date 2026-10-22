<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1792600467CreateOrganizationDictionaryPositionTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792600467CreateOrganizationDictionaryPositionTables::class)]
class Migration1792600467CreateOrganizationDictionaryPositionTablesTest extends TestCase
{
    private const TABLES = [
        'dictionary_type',
        'dictionary_type_translation',
        'dictionary_entry',
        'dictionary_entry_translation',
        'organization',
        'organization_translation',
        'organization_unit',
        'organization_unit_translation',
        'position',
        'position_translation',
        'user_organization_unit',
        'user_position',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792600467, (new Migration1792600467CreateOrganizationDictionaryPositionTables())->getCreationTimestamp());
    }

    /**
     * The migration has usually already run while the test database was installed - that is exactly
     * why every statement is written "if exists" and why it is run twice here: a second run must be
     * a no-op, not an error.
     */
    public function testCreatesAllOrganizationDictionaryAndPositionTables(): void
    {
        $migration = new Migration1792600467CreateOrganizationDictionaryPositionTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table) {
            static::assertTrue(
                TableHelper::tableExists($this->connection, $table),
                \sprintf('Table "%s" should exist after the migration', $table)
            );
        }
    }

    public function testUserOrganizationUnitMappingCarriesThePrimaryFlag(): void
    {
        (new Migration1792600467CreateOrganizationDictionaryPositionTables())->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'user_organization_unit', 'is_primary');
        static::assertTrue($column->isNotNull);
    }

    public function testUserMappingTablesReferenceUserAndTheirTarget(): void
    {
        (new Migration1792600467CreateOrganizationDictionaryPositionTables())->update($this->connection);

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'user_position', 'fk.user_position.user_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'user_position', 'fk.user_position.position_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'user_organization_unit', 'fk.user_organization_unit.user_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'user_organization_unit', 'fk.user_organization_unit.organization_unit_id'));
    }

    public function testAddsTheJsonIdColumnsOnUser(): void
    {
        (new Migration1792600467CreateOrganizationDictionaryPositionTables())->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'user', 'organization_unit_ids'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'user', 'position_ids'));
    }
}
