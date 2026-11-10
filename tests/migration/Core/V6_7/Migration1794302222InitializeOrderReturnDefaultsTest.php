<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1794302222InitializeOrderReturnDefaults;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1794302222InitializeOrderReturnDefaults::class)]
class Migration1794302222InitializeOrderReturnDefaultsTest extends TestCase
{
    use MigrationTestTrait;

    public function testMissingDefaultsMatchTheRuntimeAndExistingValuesSurvive(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('DELETE FROM system_config WHERE configuration_key LIKE \'core.orderReturn.%\' AND sales_channel_id IS NULL');
        $migration = new Migration1794302222InitializeOrderReturnDefaults();
        $migration->update($connection);
        $connection->executeStatement('UPDATE system_config SET configuration_value = :value WHERE configuration_key = \'core.orderReturn.windowDays\' AND sales_channel_id IS NULL', ['value' => '{"_value":7}']);
        $migration->update($connection);
        $rows = $connection->fetchAllKeyValue('SELECT configuration_key, configuration_value FROM system_config WHERE configuration_key LIKE \'core.orderReturn.%\' AND sales_channel_id IS NULL');
        static::assertCount(8, $rows);
        static::assertSame(7, json_decode($rows['core.orderReturn.windowDays'], true)['_value']);
        static::assertTrue(json_decode($rows['core.orderReturn.enabled'], true)['_value']);
        static::assertSame(48, json_decode($rows['core.orderReturn.approvalDeadlineHours'], true)['_value']);
        static::assertTrue(json_decode($rows['core.orderReturn.restockOnReceive'], true)['_value']);
    }
}
