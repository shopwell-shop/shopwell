<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopwell\Elasticsearch\Admin\AdminSearchRegistry;
use Shopwell\Elasticsearch\Framework\Command\ElasticsearchAdminIndexingCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchAdminIndexingCommand::class)]
class ElasticsearchAdminIndexingCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);

        $registry->expects($this->once())->method('iterate')->with(new AdminIndexingBehavior(true, [], ['promotion']));
        $commandTester = new CommandTester(new ElasticsearchAdminIndexingCommand($registry));
        $commandTester->execute(['--no-queue' => true, '--only' => 'promotion']);

        $commandTester->assertCommandIsSuccessful();
    }
}
