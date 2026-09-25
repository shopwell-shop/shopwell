<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopwell\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopwell\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Shopwell\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopwell\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopwell\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopwell\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StaticFileConfigDumper::class)]
class StaticFileConfigDumperTest extends TestCase
{
    public function testDumping(): void
    {
        $salesChannelToTheme = new StorefrontPluginConfiguration('Test');
        $loader = static::createStub(DatabaseConfigLoader::class);
        $loader->method('load')->willReturn($salesChannelToTheme);

        $privateFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $themeProvider = static::createStub(DatabaseAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        $dumper = new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $privateFileSystem,
            $temporaryFileSystem
        );

        $location = StaticFileAvailableThemeProvider::THEME_INDEX;

        $dumper->dumpConfig(Context::createDefaultContext());
        static::assertSame('{"test":"test"}', $privateFileSystem->read($location));

        $dumper->dumpConfigFromEvent();
        static::assertSame('{"test":"test"}', $privateFileSystem->read($location));
    }

    public function testDumpConfigInVar(): void
    {
        $privateFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $dumper = new StaticFileConfigDumper(
            static::createStub(DatabaseConfigLoader::class),
            static::createStub(DatabaseAvailableThemeProvider::class),
            $privateFileSystem,
            $temporaryFileSystem
        );

        $location = 'theme-files.json';
        $dump = ['test' => '123'];

        $dumper->dumpConfigInVar($location, $dump);
        static::assertJsonStringEqualsJsonString('{"test": "123"}', $temporaryFileSystem->read($location));
    }

    public function testgetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ThemeConfigChangedEvent::class => 'dumpConfigFromEvent',
                ThemeAssignedEvent::class => 'dumpConfigFromEvent',
                ThemeConfigResetEvent::class => 'dumpConfigFromEvent',
            ],
            StaticFileConfigDumper::getSubscribedEvents()
        );
    }

    public function testDumpConfigCreatesDirectoryIfNotExists(): void
    {
        $salesChannelToTheme = new StorefrontPluginConfiguration('Test');
        $loader = static::createStub(DatabaseConfigLoader::class);
        $loader->method('load')->willReturn($salesChannelToTheme);

        $privateFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $themeProvider = static::createStub(DatabaseAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        // Verify directory doesn't exist initially
        static::assertFalse($privateFileSystem->directoryExists('theme-config'));

        $dumper = new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $privateFileSystem,
            $temporaryFileSystem
        );

        $dumper->dumpConfig(Context::createDefaultContext());

        // Verify directory was created
        static::assertTrue($privateFileSystem->directoryExists('theme-config'));
        // Verify index file was created
        static::assertTrue($privateFileSystem->fileExists(StaticFileAvailableThemeProvider::THEME_INDEX));
        // Verify theme config file was created
        static::assertTrue($privateFileSystem->fileExists('theme-config/test.json'));
    }
}
