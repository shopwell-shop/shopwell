<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\AppSystemTestBehaviour;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
#[Package('discovery')]
class StorefrontPluginRegistryTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testConfigIsAddedIfItsATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagTheme');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsNotAddedIfAppIsNotActive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagTheme', false);

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertNull(
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsAddedIfHasResourcesToCompile(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagNoThemeCustomCss');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeCustomCss')
        );
    }

    public function testConfigIsNotAddedButIdentifiedAsNotThemeIfItsNotATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagNoThemeNoCss');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeNoCss')
        );

        static::assertNull(
            $registry->getConfigurations()->getThemes()->getByTechnicalName('SwagNoThemeNoCss')
        );
    }
}
