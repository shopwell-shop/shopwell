<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794630770CreatePaymentTransferApproval;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794630770CreatePaymentTransferApproval::class)]
class Migration1794630770CreatePaymentTransferApprovalTest extends TestCase
{
    public function testFreshCreationAndRepeatedMigrationPreserveExistingRecords(): void
    {
        $shared = KernelLifecycleManager::getConnection();
        $database = 'workflow_table_' . Uuid::randomHex();
        $shared->executeStatement('CREATE DATABASE `' . $database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection = new Connection(array_replace($shared->getParams(), ['dbname' => $database]), $shared->getDriver(), $shared->getConfiguration());
        try {
            $migration = new Migration1794630770CreatePaymentTransferApproval();
            static::assertSame(1794630770, $migration->getCreationTimestamp());
            $migration->update($connection);
            static::assertTrue($connection->createSchemaManager()->tablesExist(['payment_transfer_approval']));
            $record = array_merge(['id' => Uuid::randomBytes(), 'created_at' => '2026-11-14 00:00:00.000'], ['release_id' => Uuid::randomBytes(), 'instance_id' => Uuid::randomBytes(), 'initiator_id' => Uuid::randomBytes(), 'snapshot' => '{"amount":100}']);
            $connection->insert('payment_transfer_approval', $record);
            $before = $connection->fetchAssociative('SELECT * FROM payment_transfer_approval');
            $migration->update($connection);
            static::assertSame($before, $connection->fetchAssociative('SELECT * FROM payment_transfer_approval'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval'));
        } finally {
            $connection->close();
            $shared->executeStatement('DROP DATABASE `' . $database . '`');
        }
    }
}
