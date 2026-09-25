<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Shopwell\Core\Framework\Adapter\Command\CacheClearHttpCommand;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheClearAllCommand::class)]
class CacheClearHttpCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $cache = $this->createMock(CacheClearer::class);
        $cache->expects($this->once())->method('clearHttpCache');

        $command = new CacheClearHttpCommand($cache);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }
}
