<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\BundleConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\BundleConfig\StorefrontBundleConfigStyleFileResolver;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontBundleConfigStyleFileResolver::class)]
class StorefrontBundleConfigStyleFileResolverTest extends TestCase
{
    #[TestDox('resolveStyleFiles() returns an empty array when the registry has no configuration for the technical name')]
    public function testResolveStyleFilesReturnsEmptyWhenConfigurationMissing(): void
    {
        $registry = static::createStub(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $resolver = new StorefrontBundleConfigStyleFileResolver($registry);

        static::assertSame([], $resolver->resolveStyleFiles('SwagPlugin', 'custom/plugins/SwagPlugin'));
    }

    #[TestDox('resolveStyleFiles() returns style file paths joined against the bundle basePath under Resources/')]
    public function testResolveStyleFilesJoinsConfiguredPathsToBasePath(): void
    {
        $configuration = new StorefrontPluginConfiguration('SwagPlugin');
        $configuration->setStyleFiles(FileCollection::createFromArray([
            'app/storefront/src/scss/base.scss',
            'app/storefront/src/scss/overrides.scss',
        ]));

        $registry = static::createStub(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$configuration]));

        $resolver = new StorefrontBundleConfigStyleFileResolver($registry);

        static::assertSame(
            [
                'custom/plugins/SwagPlugin/Resources/app/storefront/src/scss/base.scss',
                'custom/plugins/SwagPlugin/Resources/app/storefront/src/scss/overrides.scss',
            ],
            $resolver->resolveStyleFiles('SwagPlugin', 'custom/plugins/SwagPlugin'),
        );
    }
}
