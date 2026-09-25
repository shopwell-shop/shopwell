<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\App\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Template\TemplateLoader;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Core\Test\Stub\App\StaticSourceResolver;
use Shopwell\Storefront\Framework\App\Template\IconTemplateLoader;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IconTemplateLoader::class)]
class IconTemplateLoaderTest extends TestCase
{
    private IconTemplateLoader $templateLoader;

    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../../../Theme/fixtures/Apps/theme/manifest.xml');

        $sourceResolver = new StaticSourceResolver([
            'SwagTheme' => new Filesystem(__DIR__ . '/../../../Theme/fixtures/Apps/theme'),
        ]);

        $this->templateLoader = new IconTemplateLoader(
            new TemplateLoader($sourceResolver),
            new StorefrontPluginConfigurationFactory(
                static::createStub(KernelPluginLoader::class),
                $sourceResolver,
                new SymfonyFilesystem(),
            ),
            $sourceResolver,
        );
    }

    public function testGetTemplatePathsForAppReturnsIconPaths(): void
    {
        $templates = $this->templateLoader->getTemplatePathsForApp($this->manifest);
        \sort($templates);

        static::assertSame(
            ['app/storefront/src/assets/icon-pack/custom-icons/activity.svg', 'storefront/layout/header/logo.html.twig'],
            $templates
        );
    }

    public function testGetTemplateContentForAppReturnsIconPaths(): void
    {
        static::assertStringEqualsFile(
            __DIR__ . '/../../../Theme/fixtures/Apps/theme/Resources/app/storefront/src/assets/icon-pack/custom-icons/activity.svg',
            $this->templateLoader->getTemplateContent('app/storefront/src/assets/icon-pack/custom-icons/activity.svg', $this->manifest)
        );
    }
}
