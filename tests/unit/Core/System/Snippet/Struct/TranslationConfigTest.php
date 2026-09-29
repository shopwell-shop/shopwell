<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Struct;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\DataTransfer\Language\Language;
use Shopwell\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopwell\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;
use Shopwell\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopwell\Core\System\Snippet\SnippetException;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;
use Shopwell\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationConfig::class)]
class TranslationConfigTest extends TestCase
{
    public function testTranslationConfig(): void
    {
        $repositoryUrl = new Uri('http://localhost:8000');
        $locales = ['en-GB', 'zh-CN'];
        $plugins = ['PluginA', 'PluginB'];
        $languages = new LanguageCollection([
            new Language('en-GB', 'English'),
            new Language('zh-CN', '简体中文'),
        ]);

        $excludedLocales = ['fr-FR', 'es-ES'];

        $pluginMapping = new PluginMappingCollection([
            new PluginMapping('PluginA', 'plugin-a'),
            new PluginMapping('PluginB', 'plugin-b'),
        ]);

        $metadataUrl = new Uri('http://localhost:8000/metadata.json');

        $config = new TranslationConfig(
            $repositoryUrl,
            $locales,
            $plugins,
            $languages,
            $pluginMapping,
            $metadataUrl,
            $excludedLocales,
        );

        static::assertSame($repositoryUrl, $config->repositoryUrl);
        static::assertSame($locales, $config->locales);
        static::assertSame($plugins, $config->plugins);
        static::assertSame($languages, $config->languages);
        static::assertSame($pluginMapping, $config->pluginMapping);
        static::assertSame($metadataUrl, $config->metadataUrl);
        static::assertSame($excludedLocales, $config->excludedLocales);
    }

    public function testGetMappedPluginName(): void
    {
        $pluginWithMapping = new TestPlugin(true, 'path/to/plugin');
        $pluginWithMapping->setName('PluginWithMapping');

        $pluginMapping = new PluginMappingCollection([
            new PluginMapping('PluginWithMapping', 'MappedPluginWithMapping'),
        ]);

        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            [],
            [],
            new LanguageCollection(),
            $pluginMapping,
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );

        $pluginWithoutMapping = new TestPlugin(true, 'path/to/plugin');
        $pluginWithoutMapping->setName('PluginWithoutMapping');

        $mappedName = $config->getMappedPluginName($pluginWithoutMapping);
        static::assertSame('PluginWithoutMapping', $mappedName);

        $mappedName = $config->getMappedPluginName($pluginWithMapping);
        static::assertSame('MappedPluginWithMapping', $mappedName);
    }

    public function testAssertLocalesAreConfiguredAcceptsKnownLocales(): void
    {
        $config = $this->getConfig(['en-GB', 'zh-CN']);

        $config->assertLocalesAreConfigured(['zh-CN']);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertLocalesAreConfiguredThrowsWhenEmpty(): void
    {
        $config = $this->getConfig(['en-GB', 'zh-CN']);

        $this->expectExceptionObject(SnippetException::noLocalesArgumentProvided());

        $config->assertLocalesAreConfigured([]);
    }

    public function testAssertLocalesAreConfiguredThrowsForUnknownLocales(): void
    {
        $config = $this->getConfig(['en-GB', 'zh-CN']);

        $this->expectExceptionObject(SnippetException::invalidLocalesProvided('fr-FR, es-ES', 'en-GB, zh-CN'));

        $config->assertLocalesAreConfigured(['zh-CN', 'fr-FR', 'es-ES']);
    }

    /**
     * @param list<string> $locales
     */
    private function getConfig(array $locales): TranslationConfig
    {
        return new TranslationConfig(
            new Uri('http://localhost:8000'),
            $locales,
            [],
            new LanguageCollection(),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );
    }
}
