<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1754295570DocumentActivateReturnAddress;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1754295570DocumentActivateReturnAddress::class)]
class Migration1754295570DocumentActivateReturnAddressTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->update('document_base_config', ['config' => '{}']);
        $this->connection->update('document_base_config', ['config' => null], ['name' => 'cancellation_invoice']);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1754295570, (new Migration1754295570DocumentActivateReturnAddress())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1754295570DocumentActivateReturnAddress();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentConfig = $this->connection->executeQuery('SELECT config FROM document_base_config;')->fetchAllAssociative();

        array_walk(
            $documentConfig,
            static function (array $arr): void {
                $arr['config'] = json_decode($arr['config'], true, 512, \JSON_THROW_ON_ERROR);

                static::assertTrue($arr['config']['displayReturnAddress']);
            }
        );
    }
}
