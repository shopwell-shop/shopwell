<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1763544592UpdateGroupRegistrationMailTemplates;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1763544592UpdateGroupRegistrationMailTemplates::class)]
class Migration1763544592UpdateGroupRegistrationMailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763544592, (new Migration1763544592UpdateGroupRegistrationMailTemplates())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $migration = new Migration1763544592UpdateGroupRegistrationMailTemplates();

        $error = [];
        try {
            $migration->update($this->connection);
            $migration->update($this->connection);
        } catch (\Throwable $e) {
            $error[] = $e->getMessage();
        }

        static::assertCount(0, $error, print_r($error, true));
    }
}
