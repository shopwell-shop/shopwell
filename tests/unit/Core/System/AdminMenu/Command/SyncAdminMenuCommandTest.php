<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\Command\SyncAdminMenuCommand;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(SyncAdminMenuCommand::class)]
class SyncAdminMenuCommandTest extends TestCase
{
    /**
     * @var CoreMenuSynchronizer&MockObject
     */
    private CoreMenuSynchronizer $synchronizer;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->synchronizer = $this->createMock(CoreMenuSynchronizer::class);
        $this->tester = new CommandTester(new SyncAdminMenuCommand($this->synchronizer));
    }

    public function testReportsTheNumberOfWrittenRows(): void
    {
        $this->synchronizer->expects($this->once())->method('synchronize')->willReturn(41);

        static::assertSame(Command::SUCCESS, $this->tester->execute([]));
        static::assertStringContainsString('Wrote 41 menu item row(s)', $this->tester->getDisplay());
    }

    public function testReportsAnAlreadySynchronizedTable(): void
    {
        $this->synchronizer->expects($this->once())->method('synchronize')->willReturn(0);

        static::assertSame(Command::SUCCESS, $this->tester->execute([]));
        static::assertStringContainsString('already matches the core declarations', $this->tester->getDisplay());
    }
}
