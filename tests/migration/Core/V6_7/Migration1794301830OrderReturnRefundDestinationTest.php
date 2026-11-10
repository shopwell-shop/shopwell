<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1794301830OrderReturnRefundDestination;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1794301830OrderReturnRefundDestination::class)]
class Migration1794301830OrderReturnRefundDestinationTest extends TestCase
{
    public function testAddsAnOptionalDestinationAndCanRunAgain(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $before = $connection->fetchOne('SELECT COUNT(*) FROM order_return');
        $migration = new Migration1794301830OrderReturnRefundDestination();
        $migration->update($connection);
        $migration->update($connection);
        $column = $connection->fetchAssociative('SHOW COLUMNS FROM order_return LIKE \'refund_destination\'');
        static::assertIsArray($column);
        static::assertSame('YES', $column['Null']);
        static::assertSame($before, $connection->fetchOne('SELECT COUNT(*) FROM order_return'));
    }
}
