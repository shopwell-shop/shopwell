<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Storefront\Theme\Command\ThemeDumpCommand;
use Shopwell\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeFileResolver;
use Shopwell\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeDumpCommand::class)]
class ThemeDumpCommandTest extends TestCase
{
    private StorefrontPluginRegistry&Stub $pluginRegistry;

    private ThemeFileResolver&Stub $themeFileResolver;

    /**
     * @var EntityRepository<ThemeCollection>&Stub
     */
    private EntityRepository&Stub $themeRepository;

    private ThemeFilesystemResolver&Stub $themeFilesystemResolver;

    private CommandTester $commandTester;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $dumpedConfig = null;

    protected function setUp(): void
    {
        $this->pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $this->themeFileResolver = static::createStub(ThemeFileResolver::class);
        $this->themeRepository = static::createStub(EntityRepository::class);
        $staticFileConfigDumper = static::createStub(StaticFileConfigDumper::class);
        $this->themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);

        $staticFileConfigDumper->method('dumpConfigInVar')->willReturnCallback(
            /**
             * @param array<string, mixed> $dump
             */
            function (string $filePath, array $dump): void {
                $this->dumpedConfig = $dump;
            }
        );

        $command = new ThemeDumpCommand(
            $this->pluginRegistry,
            $this->themeFileResolver,
            $this->themeRepository,
            $staticFileConfigDumper,
            $this->themeFilesystemResolver
        );

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecutesSuccessfullyWithValidThemeName(): void
    {
        $themeEntity = new ThemeEntity();
        $themeEntity->setId('theme-id');
        $themeEntity->setTechnicalName('technical-name');
        $themeEntity->setName('Theme Name');

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('count')->willReturn(1);
        $searchResult->method('getEntities')->willReturn(new ThemeCollection([$themeEntity]));

        $this->themeRepository->method('search')->willReturn($searchResult);

        $this->pluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('technical-name'),
            ])
        );

        $this->themeFileResolver->method('resolveFiles')->willReturn(['resolved' => 'files']);
        $this->themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(
            new Filesystem('')
        );

        $this->commandTester->execute(
            ['domain-url' => 'http://example.com'],
            ['theme-name' => 'technical-name']
        );

        static::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecutesSuccessfullyWithValidThemeId(): void
    {
        $themeEntity = new ThemeEntity();
        $themeEntity->setId('theme-id');
        $themeEntity->setTechnicalName('technical-name');
        $themeEntity->setName('Theme Name');

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('count')->willReturn(1);
        $searchResult->method('getEntities')->willReturn(new ThemeCollection([$themeEntity]));

        $this->themeRepository->method('search')->willReturn($searchResult);

        $this->pluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('technical-name'),
            ])
        );

        $this->themeFileResolver->method('resolveFiles')->willReturn(['resolved' => 'files']);
        $this->themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(
            new Filesystem('')
        );

        $this->commandTester->execute([
            'theme-id' => 'theme-id',
            'domain-url' => 'http://example.com',
        ]);

        static::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testFailsWhenThemeIdIsMissing(): void
    {
        $this->commandTester->execute([
            'domain-url' => 'http://example.com',
        ]);

        static::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        static::assertStringContainsString(
            '[ERROR] No theme found which is connected to a storefront sales channel',
            $this->commandTester->getDisplay()
        );
    }

    public function testFailsWhenNoThemeFound(): void
    {
        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('count')->willReturn(0);

        $this->themeRepository->method('search')->willReturn($searchResult);

        $this->commandTester->execute([
            'theme-id' => 'invalid-theme-id',
        ]);

        static::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        static::assertStringContainsString('No theme found which is connected to a storefront sales channel', $this->commandTester->getDisplay());
    }

    public function testFailsWhenNoDomainUrlProvided(): void
    {
        $themeEntity = new ThemeEntity();
        $themeEntity->setId('theme-id');
        $themeEntity->setTechnicalName('technical-name');
        $themeEntity->setName('Theme Name');

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('count')->willReturn(1);
        $searchResult->method('getEntities')->willReturn(new ThemeCollection([$themeEntity]));

        $this->themeRepository->method('search')->willReturn($searchResult);

        $this->pluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('technical-name'),
            ])
        );

        $this->themeFileResolver->method('resolveFiles')->willReturn(['resolved' => 'files']);
        $this->themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(
            static::createStub(Filesystem::class)
        );

        $this->commandTester->execute([
            'theme-id' => 'theme-id',
        ]);

        static::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        static::assertStringContainsString('No domain URL for theme', $this->commandTester->getDisplay());
    }

    public function testResolvesSingleDomainAutomaticallyWithoutInteraction(): void
    {
        $this->arrangeThemeDump($this->createThemeEntityWithDomains(['http://single.example.com']));

        $this->commandTester->execute(['theme-id' => 'theme-id'], ['interactive' => false]);

        static::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        static::assertIsArray($this->dumpedConfig);
        static::assertSame('http://single.example.com', $this->dumpedConfig['domainUrl']);
    }

    public function testResolvesDomainWithoutInteractionWhenDomainUrlArgumentIsEmpty(): void
    {
        $this->arrangeThemeDump($this->createThemeEntityWithDomains(['http://single.example.com']));

        $this->commandTester->execute(
            ['theme-id' => 'theme-id', 'domain-url' => ''],
            ['interactive' => false]
        );

        static::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        static::assertIsArray($this->dumpedConfig);
        static::assertSame('http://single.example.com', $this->dumpedConfig['domainUrl']);
    }

    public function testUsesFirstDomainAndWarnsWithoutInteractionWhenMoreThanOneDomainExists(): void
    {
        $this->arrangeThemeDump($this->createThemeEntityWithDomains([
            'http://first.example.com',
            'http://second.example.com',
        ]));

        $this->commandTester->execute(['theme-id' => 'theme-id'], ['interactive' => false]);

        static::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        static::assertIsArray($this->dumpedConfig);
        static::assertSame('http://first.example.com', $this->dumpedConfig['domainUrl']);
        static::assertStringContainsString(
            'More than one domain URL is available',
            $this->commandTester->getDisplay()
        );
        static::assertStringContainsString('http://first.example.com', $this->commandTester->getDisplay());
    }

    public function testAsksForDomainUrlInteractivelyWhenMoreThanOneDomainExists(): void
    {
        $this->arrangeThemeDump($this->createThemeEntityWithDomains([
            'http://first.example.com',
            'http://second.example.com',
        ]));

        $this->commandTester->setInputs(['http://second.example.com']);
        $this->commandTester->execute(['theme-id' => 'theme-id']);

        static::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        static::assertStringContainsString('Please select a domain url:', $this->commandTester->getDisplay());
        static::assertIsArray($this->dumpedConfig);
        static::assertSame('http://second.example.com', $this->dumpedConfig['domainUrl']);
    }

    public function testFailsWithoutInteractionWhenNoDomainExists(): void
    {
        $this->arrangeThemeDump($this->createThemeEntityWithDomains([]));

        $this->commandTester->execute(['theme-id' => 'theme-id'], ['interactive' => false]);

        static::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        static::assertStringContainsString('No domain URL for theme', $this->commandTester->getDisplay());
        static::assertNull($this->dumpedConfig);
    }

    private function arrangeThemeDump(ThemeEntity $themeEntity): void
    {
        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('count')->willReturn(1);
        $searchResult->method('getEntities')->willReturn(new ThemeCollection([$themeEntity]));

        $this->themeRepository->method('search')->willReturn($searchResult);

        $this->pluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('technical-name'),
            ])
        );

        $this->themeFileResolver->method('resolveFiles')->willReturn(['resolved' => 'files']);
        $this->themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(
            new Filesystem('')
        );
    }

    /**
     * @param list<string> $urls
     */
    private function createThemeEntityWithDomains(array $urls): ThemeEntity
    {
        $domains = [];
        foreach ($urls as $index => $url) {
            $domain = new SalesChannelDomainEntity();
            $domain->setId('domain-' . $index);
            $domain->setUrl($url);
            $domains[] = $domain;
        }

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannel->setDomains(new SalesChannelDomainCollection($domains));

        $themeEntity = new ThemeEntity();
        $themeEntity->setId('theme-id');
        $themeEntity->setTechnicalName('technical-name');
        $themeEntity->setName('Theme Name');
        $themeEntity->setSalesChannels(new SalesChannelCollection([$salesChannel]));

        return $themeEntity;
    }
}
