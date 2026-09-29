<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1773826242RenameAgenticCommerceSalesChannelType;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1773826242RenameAgenticCommerceSalesChannelType::class)]
class Migration1773826242RenameAgenticCommerceSalesChannelTypeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773826242, (new Migration1773826242RenameAgenticCommerceSalesChannelType())->getCreationTimestamp());
    }

    public function testMigrationRenamesAgenticCommerceTranslations(): void
    {
        $salesChannelTypeId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE);

        $existingTranslations = $this->connection->fetchAllAssociative(
            'SELECT sctt.language_id, loc.code
             FROM sales_channel_type_translation sctt
             INNER JOIN language lang ON lang.id = sctt.language_id
             INNER JOIN locale loc ON loc.id = lang.locale_id
             WHERE sctt.sales_channel_type_id = :salesChannelTypeId',
            ['salesChannelTypeId' => $salesChannelTypeId]
        );

        static::assertNotEmpty($existingTranslations);

        $this->connection->update(
            'sales_channel_type_translation',
            [
                'name' => 'Old Name',
                'manufacturer' => 'Old Manufacturer',
                'description' => 'Old Description',
            ],
            ['sales_channel_type_id' => $salesChannelTypeId]
        );

        $migration = new Migration1773826242RenameAgenticCommerceSalesChannelType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT sctt.name, sctt.manufacturer, sctt.description, loc.code
             FROM sales_channel_type_translation sctt
             INNER JOIN language lang ON lang.id = sctt.language_id
             INNER JOIN locale loc ON loc.id = lang.locale_id
             WHERE sctt.sales_channel_type_id = :salesChannelTypeId',
            ['salesChannelTypeId' => $salesChannelTypeId]
        );

        static::assertCount(\count($existingTranslations), $translations);

        foreach ($translations as $translation) {
            static::assertSame('Agentic Commerce', $translation['name']);
            static::assertSame('Shopwell', $translation['manufacturer']);

            if ($translation['code'] === 'zh-CN') {
                static::assertSame('面向 Agentic 商务平台的销售渠道', $translation['description']);

                continue;
            }

            static::assertSame('Sales channel for agentic commerce platforms', $translation['description']);
        }
    }
}
