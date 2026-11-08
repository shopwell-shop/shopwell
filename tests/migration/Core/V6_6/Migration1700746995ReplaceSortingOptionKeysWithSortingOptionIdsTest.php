<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_6\Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds::class)]
class Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIdsTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds $migration;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->migration = new Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1700746995, (new Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->addSalesChannelSorting();

        $sortingIds = $this->getSortingIds();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $defaultSortingConfigs = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey',
            ['configKey' => 'core.listing.defaultSorting']
        );

        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[0]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            $sortingIds['price-desc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[1]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            $sortingIds['topseller'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[2]['configuration_value'], true)['_value'])
        );
    }

    public function testMigrationForCorruptedEntries(): void
    {
        $this->addCorruptedSalesChannelSorting();

        $sortingIds = $this->getSortingIds();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $defaultSortingConfigs = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey',
            ['configKey' => 'core.listing.defaultSorting']
        );

        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[0]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            [
                '_valu' => 'price-desc',
            ],
            json_decode($defaultSortingConfigs[1]['configuration_value'], true)
        );
        static::assertSame(
            [
                '_value' => 'njdsfk;nsjdgk;',
            ],
            json_decode($defaultSortingConfigs[2]['configuration_value'], true)
        );
    }

    private function addSalesChannelSorting(): void
    {
        $sortings = [
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_value": "price-desc"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_value": "topseller"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
        ];

        $this->connection->insert('system_config', $sortings[0]);
        $this->connection->insert('system_config', $sortings[1]);
    }

    private function addCorruptedSalesChannelSorting(): void
    {
        $sortings = [
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_valu": "price-desc"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_value": "njdsfk;nsjdgk;"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
        ];

        $this->connection->insert('system_config', $sortings[0]);
        $this->connection->insert('system_config', $sortings[1]);
    }

    /**
     * @return string[]
     */
    private function getSortingIds(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT url_key, id FROM product_sorting');
    }
}
