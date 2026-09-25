<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Theme\Command\ThemeChangeCommand;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeService;
use Shopwell\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeChangeCommand::class)]
class ThemeChangeCommandTest extends TestCase
{
    public function testItDeletesUnusedDirectoriesAfterChange(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())
            ->method('deleteUnusedDirectories')
            ->willReturn(2);

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItSkipsCleanupWhenNoCleanupOptionIsPassed(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->never())
            ->method('deleteUnusedDirectories');

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true, '--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItCleansUpEvenWhenCompilationIsSkipped(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())
            ->method('deleteUnusedDirectories')
            ->willReturn(0);

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true, '--no-compile' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    private function createCommand(UnusedThemeDirectoryDeleter $unusedThemeDirectoryDeleter): ThemeChangeCommand
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setUniqueIdentifier($salesChannel->getId());
        $salesChannel->setName('Storefront');

        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $theme->setUniqueIdentifier($theme->getId());
        $theme->setTechnicalName('Storefront');

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])]);
        /** @var StaticEntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = new StaticEntityRepository([new ThemeCollection([$theme])]);

        $command = new ThemeChangeCommand(
            static::createStub(ThemeService::class),
            static::createStub(StorefrontPluginRegistry::class),
            $salesChannelRepository,
            $themeRepository,
            $unusedThemeDirectoryDeleter
        );

        // register the command on an application so the "question" helper set is available
        (new Application())->addCommand($command);

        return $command;
    }
}
