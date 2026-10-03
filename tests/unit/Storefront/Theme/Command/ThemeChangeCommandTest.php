<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Theme\Command\ThemeChangeCommand;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeChangeCommand::class)]
class ThemeChangeCommandTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed together with the `--no-cleanup` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testItStillAcceptsTheDeprecatedNoCleanupOption(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setUniqueIdentifier($salesChannel->getId());
        $salesChannel->setName('Storefront');

        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $theme->setUniqueIdentifier($theme->getId());
        $theme->setTechnicalName('Storefront');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('assignTheme')
            ->with($theme->getId(), $salesChannel->getId(), static::anything(), false);

        $command = new ThemeChangeCommand(
            $themeService,
            static::createStub(StorefrontPluginRegistry::class),
            new StaticEntityRepository([new SalesChannelCollection([$salesChannel])]),
            new StaticEntityRepository([new ThemeCollection([$theme])])
        );

        // register the command on an application so the "question" helper set is available
        (new Application())->addCommand($command);

        $commandTester = new CommandTester($command);

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true, '--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }
}
