<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationCollection;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Core\Framework\Plugin\Context\ActivateContext;
use Shopwell\Core\Framework\Plugin\Context\UpdateContext;
use Shopwell\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\Framework\Plugin\PluginLifecycleService;
use Shopwell\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopwell\Storefront\Theme\ThemeLifecycleHandler;
use Shopwell\Storefront\Theme\ThemeLifecycleService;
use SwagTestPlugin\SwagTestPlugin;

/**
 * @internal
 */
#[Package('discovery')]
class PluginLifecycleSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PluginTestsHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addTestPluginToKernel(
            __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/plugins/SwagTestPlugin',
            'SwagTestPlugin'
        );
    }

    public function testDoesNotAddPluginStorefrontConfigurationToConfigurationCollectionIfItIsAddedAlready(): void
    {
        $context = Context::createDefaultContext();
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                static::createStub(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                static::createStub(MigrationCollection::class)
            )
        );
        $storefrontPluginConfigMock = new StorefrontPluginConfiguration('SwagTest');
        // Plugin storefront config is already added here
        $storefrontPluginConfigCollection = new StorefrontPluginConfigurationCollection([$storefrontPluginConfigMock]);

        $pluginConfigurationFactory = static::createStub(AbstractStorefrontPluginConfigurationFactory::class);
        $pluginConfigurationFactory->method('createFromBundle')->willReturn($storefrontPluginConfigMock);
        $storefrontPluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $storefrontPluginRegistry->method('getConfigurations')->willReturn($storefrontPluginConfigCollection);
        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->once())->method('handleThemeInstallOrUpdate')->with(
            $storefrontPluginConfigMock,
            // This ensures the plugin storefront config is not added twice
            static::equalTo($storefrontPluginConfigCollection),
            $context,
        );

        $subscriber = new PluginLifecycleSubscriber(
            $storefrontPluginRegistry,
            __DIR__,
            $pluginConfigurationFactory,
            $handler,
            static::createStub(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testAddsThePluginStorefrontConfigurationToConfigurationCollectionIfItWasNotAddedAlready(): void
    {
        $context = Context::createDefaultContext();
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                static::createStub(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                static::createStub(MigrationCollection::class)
            )
        );
        $storefrontPluginConfigMock = new StorefrontPluginConfiguration('SwagTest');
        // Plugin storefront config is not added here
        $storefrontPluginConfigCollection = new StorefrontPluginConfigurationCollection([]);

        $pluginConfigurationFactory = static::createStub(AbstractStorefrontPluginConfigurationFactory::class);
        $pluginConfigurationFactory->method('createFromBundle')->willReturn($storefrontPluginConfigMock);
        $storefrontPluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $storefrontPluginRegistry->method('getConfigurations')->willReturn($storefrontPluginConfigCollection);
        $collectionWithPluginConfig = clone $storefrontPluginConfigCollection;
        $collectionWithPluginConfig->add($storefrontPluginConfigMock);
        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->once())->method('handleThemeInstallOrUpdate')->with(
            $storefrontPluginConfigMock,
            // This ensures the plugin storefront config was added in the subscriber
            static::equalTo($collectionWithPluginConfig),
            $context,
        );

        $subscriber = new PluginLifecycleSubscriber(
            $storefrontPluginRegistry,
            __DIR__,
            $pluginConfigurationFactory,
            $handler,
            static::createStub(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnActivate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                static::createStub(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                static::createStub(MigrationCollection::class)
            )
        );

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->never())->method('handleThemeInstallOrUpdate');

        $subscriber = new PluginLifecycleSubscriber(
            static::createStub(StorefrontPluginRegistry::class),
            __DIR__,
            static::createStub(AbstractStorefrontPluginConfigurationFactory::class),
            $handler,
            static::createStub(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnUpdate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPreUpdateEvent(
            $this->getPlugin(),
            new UpdateContext(
                static::createStub(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                static::createStub(MigrationCollection::class),
                '1.0.1'
            )
        );

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->never())->method('handleThemeInstallOrUpdate');

        $subscriber = new PluginLifecycleSubscriber(
            static::createStub(StorefrontPluginRegistry::class),
            __DIR__,
            static::createStub(AbstractStorefrontPluginConfigurationFactory::class),
            $handler,
            static::createStub(ThemeLifecycleService::class),
        );

        $subscriber->pluginUpdate($event);
    }

    public function testPostUpdateDoesNothingWhenAssetBuildingIsDisabled(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPostUpdateEvent(
            $this->getPlugin(),
            new UpdateContext(
                static::createStub(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                static::createStub(MigrationCollection::class),
                '1.0.1'
            )
        );

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->expects($this->never())->method('getConfigurations');

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $subscriber = new PluginLifecycleSubscriber(
            $registry,
            __DIR__,
            static::createStub(AbstractStorefrontPluginConfigurationFactory::class),
            $handler,
            static::createStub(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostUpdate($event);
    }

    private function getPlugin(): PluginEntity
    {
        return (new PluginEntity())
            ->assign([
                'name' => 'SwagTestPlugin',
                'path' => (new \ReflectionClass(SwagTestPlugin::class))->getFileName(),
                'baseClass' => SwagTestPlugin::class,
            ]);
    }
}
