<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Command\Install;
use Shopwell\Core\Service\LifecycleManager;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Install::class)]
class InstallTest extends TestCase
{
    public function testCommandWhenNoServicesAreInstalled(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->method('enabled')
            ->willReturn(true);
        $manager->expects($this->once())->method('reconcile')->willReturn([]);

        $command = new Install($manager);
        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertStringContainsString('No services were installed', $tester->getDisplay());
    }

    public function testCommandWhenServicesAreDisabled(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->method('enabled')
            ->willReturn(false);
        $manager->expects($this->never())->method('reconcile');

        $command = new Install($manager);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Install::FAILURE, $exitCode);
        static::assertStringContainsString('Services are disabled. Please enable them to install services.', $tester->getDisplay());
    }

    public function testCommandWritesListOfInstalledServices(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->method('enabled')
            ->willReturn(true);
        $manager->expects($this->once())->method('reconcile')->willReturn([
            'MyCoolService1',
            'MyCoolService2',
        ]);

        $command = new Install($manager);
        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertStringContainsString('MyCoolService1', $tester->getDisplay());
        static::assertStringContainsString('MyCoolService2', $tester->getDisplay());
    }
}
