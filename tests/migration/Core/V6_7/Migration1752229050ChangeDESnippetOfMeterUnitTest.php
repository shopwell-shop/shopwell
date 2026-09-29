<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1742199549MeasurementSystemTable;
use Shopwell\Core\Migration\V6_7\Migration1742199550MeasurementDisplayUnitTable;
use Shopwell\Core\Migration\V6_7\Migration1752229050ChangeDESnippetOfMeterUnit;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1752229050ChangeDESnippetOfMeterUnit::class)]
class Migration1752229050ChangeDESnippetOfMeterUnitTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system`');

        $systemMigration = new Migration1742199549MeasurementSystemTable();
        $systemMigration->update($this->connection);

        $unitMigration = new Migration1742199550MeasurementDisplayUnitTable();
        $unitMigration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1752229050, (new Migration1752229050ChangeDESnippetOfMeterUnit())->getCreationTimestamp());
    }

    public function testMigrationUpdatesChineseTranslation(): void
    {
        $zhCnLanguageId = $this->getZhCnLanguageId();
        $meterUnitId = $this->getMeterUnitId();

        if (!$zhCnLanguageId || !$meterUnitId) {
            static::markTestSkipped('Chinese language or meter unit not found');
        }

        $this->connection->executeStatement('
            UPDATE `measurement_display_unit_translation`
            SET `name` = :name, `updated_at` = NULL
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'name' => '仪表',
            'unitId' => $meterUnitId,
            'languageId' => $zhCnLanguageId,
        ]);

        $translationBefore = $this->connection->fetchOne('
            SELECT `name` FROM `measurement_display_unit_translation`
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'unitId' => $meterUnitId,
            'languageId' => $zhCnLanguageId,
        ]);

        static::assertSame('仪表', $translationBefore);

        $migration = new Migration1752229050ChangeDESnippetOfMeterUnit();
        $migration->update($this->connection);
        $migration->update($this->connection);

        // Verify the translation was updated
        $translationAfter = $this->connection->fetchOne('
            SELECT `name` FROM `measurement_display_unit_translation`
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'unitId' => $meterUnitId,
            'languageId' => $zhCnLanguageId,
        ]);

        static::assertSame('米', $translationAfter);
    }

    public function testMigrationDoesNotUpdateModifiedTranslation(): void
    {
        $zhCnLanguageId = $this->getZhCnLanguageId();
        $meterUnitId = $this->getMeterUnitId();

        if (!$zhCnLanguageId || !$meterUnitId) {
            static::markTestSkipped('Chinese language or meter unit not found');
        }

        // Set a different Chinese translation with updated_at (simulating manual modification)
        $this->connection->executeStatement('
            UPDATE `measurement_display_unit_translation`
            SET `name` = :name, `updated_at` = :updatedAt
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'name' => '手动修改',
            'unitId' => $meterUnitId,
            'languageId' => $zhCnLanguageId,
            'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1752229050ChangeDESnippetOfMeterUnit();
        $migration->update($this->connection);

        $translationAfter = $this->connection->fetchOne('
            SELECT `name` FROM `measurement_display_unit_translation`
            WHERE `measurement_display_unit_id` = :unitId AND `language_id` = :languageId
        ', [
            'unitId' => $meterUnitId,
            'languageId' => $zhCnLanguageId,
        ]);

        static::assertSame('手动修改', $translationAfter);
    }

    private function getZhCnLanguageId(): ?string
    {
        $result = $this->connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = "zh-CN"
        ');

        if ($result === false || Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) === $result) {
            return null;
        }

        return (string) $result;
    }

    private function getMeterUnitId(): ?string
    {
        $result = $this->connection->fetchOne('SELECT id FROM measurement_display_unit WHERE short_name = "m"');

        return $result ?: null;
    }
}
