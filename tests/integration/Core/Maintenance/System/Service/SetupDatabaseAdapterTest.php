<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Maintenance\System\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopwell\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopwell\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 */
#[Package('framework')]
class SetupDatabaseAdapterTest extends TestCase
{
    public function testInitialize(): void
    {
        $connectionInfo = DatabaseConnectionInformation::fromEnv();

        $testDbName = 'test_schema';
        $connection = DatabaseConnectionFactory::createConnection($connectionInfo, true);
        $setupDatabaseAdapter = new SetupDatabaseAdapter();

        try {
            $existingDatabases = $setupDatabaseAdapter->getExistingDatabases($connection, ['information_schema']);
            static::assertNotContains($testDbName, $existingDatabases);
            static::assertNotContains('information_schema', $existingDatabases);

            $setupDatabaseAdapter->createDatabase($connection, $testDbName);

            static::assertContains($testDbName, $setupDatabaseAdapter->getExistingDatabases($connection, []));
            static::assertFalse($setupDatabaseAdapter->hasShopwellTables($connection, $testDbName));

            $setupDatabaseAdapter->initializeShopwellDb($connection, $testDbName);

            static::assertTrue($setupDatabaseAdapter->hasShopwellTables($connection, $testDbName));
        } finally {
            $setupDatabaseAdapter->dropDatabase($connection, $testDbName);

            static::assertNotContains($testDbName, $setupDatabaseAdapter->getExistingDatabases($connection, []));
        }
    }
}
