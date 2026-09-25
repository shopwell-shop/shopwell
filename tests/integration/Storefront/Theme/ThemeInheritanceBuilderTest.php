<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Bundle;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Storefront\Storefront;
use Shopwell\Storefront\Test\Theme\ThemeRuntimeConfigTestService;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\Twig\ThemeInheritanceBuilder;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\ConfigWithoutStorefrontDefined\ConfigWithoutStorefrontDefined;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\PluginWildcardAndExplicit\PluginWildcardAndExplicit;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimpleTheme\SimpleTheme;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\ThemeWithoutStorefront\ThemeWithoutStorefront;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeInheritanceBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractStorefrontPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->configFactory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);
    }

    public function testInheritanceWithConfig(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['InheritanceWithConfig' => 1, 'Storefront' => 1],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertSame(['InheritanceWithConfig', 'Storefront'], array_keys($inheritance));
    }

    public function testEnsurePlugins(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['InheritanceWithConfig' => 1, 'Storefront' => 1, 'PayPal' => 1],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertSame(['PayPal', 'InheritanceWithConfig', 'Storefront'], array_keys($inheritance));
    }

    public function testConfigWithoutStorefrontDefined(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ConfigWithoutStorefrontDefined()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['ConfigWithoutStorefrontDefined' => 1, 'Storefront' => 1, 'PayPal' => 1],
            ['ConfigWithoutStorefrontDefined' => true]
        );

        static::assertSame(['PayPal', 'ConfigWithoutStorefrontDefined'], array_keys($inheritance));
    }

    public function testPluginWildcardAndExplicit(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new PluginWildcardAndExplicit()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('CustomProducts', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['PluginWildcardAndExplicit' => 1, 'Storefront' => 1, 'PayPal' => 1, 'CustomProducts' => 1],
            ['PluginWildcardAndExplicit' => true, 'Storefront' => true]
        );

        static::assertSame(['CustomProducts', 'PluginWildcardAndExplicit', 'PayPal', 'Storefront'], array_keys($inheritance));
    }

    public function testThemeWithoutStorefront(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ThemeWithoutStorefront()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('CustomProducts', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['ThemeWithoutStorefront' => 1, 'Storefront' => 1, 'PayPal' => 1, 'CustomProducts' => 1],
            ['ThemeWithoutStorefront' => true, 'Storefront' => true]
        );

        static::assertSame(['CustomProducts', 'ThemeWithoutStorefront', 'PayPal'], array_keys($inheritance));
    }

    public function testMultiInheritance(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin')),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeA', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeB', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeC', SimpleTheme::class)),

            // paypal is a plugin and should be registered
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),

            // theme d is not included in theme.json
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeD', SimpleTheme::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['ThemeWithMultiInheritance' => 1, 'ThemeA' => 1, 'ThemeB' => 1, 'ThemeC' => 1, 'ThemeD' => 1, 'PayPal' => 1],
            ['ThemeWithMultiInheritance' => true]
        );

        static::assertSame(
            ['ThemeWithMultiInheritance', 'ThemeC', 'PayPal', 'ThemeB', 'ThemeA'],
            array_keys($inheritance)
        );
    }

    /**
     * @param class-string $pluginClass
     */
    private function getMockedPlugin(string $pluginName, string $pluginClass): Bundle
    {
        /** @var Bundle $bundle */
        $bundle = new $pluginClass(true, __DIR__ . '/fixtures/SimplePlugin');

        $reflection = new \ReflectionClass($pluginClass);
        $reflection->getProperty('name')->setValue($bundle, $pluginName);

        return $bundle;
    }

    private function createInheritanceBuilder(StorefrontPluginConfigurationCollection $configurationCollection): ThemeInheritanceBuilder
    {
        $themeRuntimeConfigService = new ThemeRuntimeConfigTestService($configurationCollection);

        return new ThemeInheritanceBuilder($themeRuntimeConfigService);
    }
}
