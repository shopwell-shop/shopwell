<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_6\Migration1735112885AddDefaultSearchResultSorting;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1735112885AddDefaultSearchResultSorting::class)]
class Migration1735112885AddDefaultSearchResultSortingTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => 'core.listing.defaultSearchResultSorting']);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1735112885, (new Migration1735112885AddDefaultSearchResultSorting())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertCount(0, $this->getConfig());

        $migration = new Migration1735112885AddDefaultSearchResultSorting();
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.listing.defaultSearchResultSorting', $record['configuration_key']);

        $value = \sprintf('{"_value": "%s"}', Uuid::randomHex());
        $this->connection->update('system_config', ['configuration_value' => $value], ['configuration_key' => 'core.listing.defaultSearchResultSorting']);

        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.listing.defaultSearchResultSorting', $record['configuration_key']);
        static::assertSame($value, $record['configuration_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.listing.defaultSearchResultSorting\''
        ) ?: [];
    }
}
