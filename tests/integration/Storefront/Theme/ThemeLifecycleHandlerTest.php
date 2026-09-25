<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Util\AccessKeyHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Storefront;
use Shopwell\Storefront\Theme\Exception\ThemeException;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeLifecycleHandler;
use Shopwell\Storefront\Theme\ThemeLifecycleService;
use Shopwell\Storefront\Theme\ThemeSalesChannel;
use Shopwell\Storefront\Theme\ThemeSalesChannelCollection;
use Shopwell\Storefront\Theme\ThemeService;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\PluginWithAdditionalBundles\PluginWithAdditionalBundles;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimplePluginWithoutCompilation\SimplePluginWithoutCompilation;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimpleTheme\SimpleTheme;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MockObject&ThemeService $themeServiceMock;

    private MockObject&StorefrontPluginRegistry $configurationRegistryMock;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private AbstractStorefrontPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->themeServiceMock = $this->createMock(ThemeService::class);

        $this->configurationRegistryMock = $this->createMock(StorefrontPluginRegistry::class);

        $this->themeLifecycleHandler = new ThemeLifecycleHandler(
            static::getContainer()->get(ThemeLifecycleService::class),
            $this->themeServiceMock,
            static::getContainer()->get('theme.repository'),
            $this->configurationRegistryMock,
            static::getContainer()->get(Connection::class)
        );

        $this->configFactory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `theme_sales_channel`');
        $this->assignThemeToDefaultSalesChannel();
    }

    public function testHandleThemeInstallOrUpdateWillRecompileThemeIfNecessary(): void
    {
        // the registry is only consulted on uninstall, after the deactivation succeeded
        $this->configurationRegistryMock->expects($this->never())->method(static::anything());

        $installConfig = $this->configFactory->createFromBundle(new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'));

        $this->themeServiceMock->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                static::isString(),
                static::isInstanceOf(Context::class),
                static::callback(static fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeInstallOrUpdateWillRecompilePluginWithSubBundles(): void
    {
        // the registry is only consulted on uninstall, after the deactivation succeeded
        $this->configurationRegistryMock->expects($this->never())->method(static::anything());

        $installConfig = $this->configFactory->createFromBundle(new PluginWithAdditionalBundles(true, __DIR__ . '/fixtures/PluginWithSubBundles'));

        $this->themeServiceMock->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                static::isString(),
                static::isInstanceOf(Context::class),
                static::callback(static fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeInstallOrUpdateWithInheritance(): void
    {
        // the registry is only consulted on uninstall, after the deactivation succeeded
        $this->configurationRegistryMock->expects($this->never())->method(static::anything());

        // the fixture theme ships no files to compile, so nothing reaches the theme service
        $this->themeServiceMock->expects($this->never())->method(static::anything());

        $installConfig = $this->configFactory->createFromBundle(new InheritanceWithConfig());

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());

        /** @var EntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = static::getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $installConfig->getTechnicalName()));
        $criteria->addAssociation('parentThemes');

        $themeEntity = $themeRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(ThemeEntity::class, $themeEntity);
        static::assertSame($installConfig->getTechnicalName(), $themeEntity->getTechnicalName());
    }

    public function testHandleThemeInstallOrUpdateWillRecompileOnlyTouchedTheme(): void
    {
        // the registry is only consulted on uninstall, after the deactivation succeeded
        $this->configurationRegistryMock->expects($this->never())->method(static::anything());

        $salesChannelId = $this->createSalesChannel();
        $themeId = $this->createTheme('SimpleTheme', $salesChannelId);
        $installConfig = $this->configFactory->createFromBundle(new SimpleTheme());
        $installConfig->setStyleFiles(FileCollection::createFromArray(['onlyForFile']));

        $this->themeServiceMock->expects($this->once())
            ->method('compileThemeById')
            ->with(
                $themeId,
                static::isInstanceOf(Context::class),
                static::callback(static fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillRecompileThemeIfNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'));

        $this->themeServiceMock->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                static::isString(),
                static::isInstanceOf(Context::class),
                static::callback(static fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 1 && (
                    (
                        $configs->first() instanceof StorefrontPluginConfiguration
                        ? $configs->first()->getTechnicalName()
                        : ''
                    ) === 'Storefront'
                ))
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $uninstalledConfig,
        ]);

        $this->configurationRegistryMock->expects($this->once())
            ->method('getConfigurations')
            ->willReturn($configs);

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillNotRecompileThemeIfNotNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePluginWithoutCompilation());

        $this->themeServiceMock->expects($this->never())
            ->method('compileTheme');

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $uninstalledConfig,
        ]);

        $this->configurationRegistryMock->expects($this->once())
            ->method('getConfigurations')
            ->willReturn($configs);

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillThrowExceptionIfThemeIsStillInUse(): void
    {
        // the registry is only consulted on uninstall, after the deactivation succeeded
        $this->configurationRegistryMock->expects($this->never())->method(static::anything());

        $uninstalledConfig = $this->configFactory->createFromBundle(new SimpleTheme());
        $uninstalledConfig->setStyleFiles(new FileCollection());
        $uninstalledConfig->setScriptFiles(new FileCollection());

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $uninstalledConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($uninstalledConfig, $configs, Context::createDefaultContext());
        $this->assignThemeToDefaultSalesChannel('SimpleTheme');

        $scCollection = new ThemeSalesChannelCollection();
        $scCollection->add(new ThemeSalesChannel(Uuid::randomHex(), Uuid::randomHex()));
        $this->themeServiceMock->expects($this->once())
            ->method('getThemeDependencyMapping')
            ->willReturn($scCollection);

        $placeholderSalesChannelId = 'sc-id';
        $this->expectExceptionObject(ThemeException::themeAssignmentException(
            'Simple theme',
            ['Simple theme' => [$placeholderSalesChannelId]],
            [],
            [$placeholderSalesChannelId => 'Headless'],
        ));

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    private function assignThemeToDefaultSalesChannel(?string $themeName = null): void
    {
        $themeRepository = static::getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        if ($themeName) {
            $criteria->addFilter(new EqualsFilter('technicalName', $themeName));
        }

        $themeId = $themeRepository->searchIds($criteria, $context)->firstId();

        $themeRepository->update([
            [
                'id' => $themeId,
                'salesChannels' => [
                    [
                        'id' => TestDefaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ], $context);
    }

    private function createTheme(string $name, string $salesChannelId): string
    {
        $id = Uuid::randomHex();

        $repository = static::getContainer()->get('theme.repository');

        $repository->create([
            [
                'id' => $id,
                'technicalName' => $name,
                'name' => $name,
                'author' => 'test',
                'active' => true,
                'salesChannels' => [
                    [
                        'id' => $salesChannelId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $id;
    }

    private function createSalesChannel(): string
    {
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $id = Uuid::randomHex();
        $payload = [[
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'active' => true,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ]];

        $salesChannelRepository->create($payload, Context::createDefaultContext());

        return $id;
    }
}
