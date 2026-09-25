<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Command\ElasticsearchUpdateMappingCommand;
use Shopwell\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchUpdateMappingCommand::class)]
class ElasticsearchUpdateMappingCommandTest extends TestCase
{
    public function testUpdate(): void
    {
        $updater = $this->createMock(IndexMappingUpdater::class);
        $updater
            ->expects($this->once())
            ->method('update');

        $command = new ElasticsearchUpdateMappingCommand(
            $updater
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
    }
}
