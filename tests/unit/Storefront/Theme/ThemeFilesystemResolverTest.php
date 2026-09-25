<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Kernel;
use Shopwell\Core\Test\Stub\App\StaticSourceResolver;
use Shopwell\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\ThemeFilesystemResolver;
use Shopwell\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeFilesystemResolver::class)]
class ThemeFilesystemResolverTest extends TestCase
{
    public function testGetFilesystemForStorefrontUsesBundleRootWithoutResourcePrefix(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $bundle = new MockStorefront();
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'Storefront' => $bundle,
        ]);

        $kernel->expects($this->once())->method('getBundle')->willReturnMap([
            ['Storefront', $bundle],
        ]);

        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver(),
            $kernel
        );

        $pluginConfig = new StorefrontPluginConfiguration('Storefront');
        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertSame($bundle->getPath(), $fs->location);
    }

    public function testGetFilesystemDelegatesToAppSourceResolverForApps(): void
    {
        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver([
                'CoolApp' => new StaticFilesystem(),
            ]),
            static::createStub(Kernel::class)
        );

        $pluginConfig = new StorefrontPluginConfiguration('CoolApp');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertSame('/app-root', $fs->location);
    }

    public function testGetFilesystemForPluginUsesBundleBasePath(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->expects($this->once())->method('getPath')->willReturn('/some/project/custom/plugins/CoolPlugin');
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'CoolPlugin' => $bundle,
        ]);

        $kernel->expects($this->once())->method('getBundle')->willReturnMap([
            ['CoolPlugin', $bundle],
        ]);

        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver(),
            $kernel
        );

        $pluginConfig = new StorefrontPluginConfiguration('CoolPlugin');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertSame('/some/project/custom/plugins/CoolPlugin', $fs->location);
    }
}
