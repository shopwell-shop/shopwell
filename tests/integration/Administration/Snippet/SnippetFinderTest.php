<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Administration\Snippet;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Administration\Snippet\SnippetFinder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Core\Framework\Plugin\KernelPluginCollection;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Kernel;
use Shopwell\Core\System\Snippet\Files\SnippetFileLoader;
use Shopwell\Core\System\Snippet\Service\TranslationLoader;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;
use Shopwell\Tests\Unit\Core\System\Snippet\Service\TestableTranslationConfigLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
class SnippetFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Filesystem $localFilesystem;

    /**
     * In-memory stand-in for the downloaded translations, read by the snippet finder as the "installed" translation set.
     */
    private Flysystem $translationFilesystem;

    private TranslationConfig $translationConfig;

    private TranslationLoader $translationLoader;

    private FakePlugin $plugin;

    protected function setUp(): void
    {
        $this->localFilesystem = new Filesystem();
        $this->translationFilesystem = new Flysystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $configLoader = new TestableTranslationConfigLoader($this->localFilesystem);
        $configLoader->setRelativeConfigurationPath(__DIR__ . '/fixtures/translationConfig');
        $this->translationConfig = $configLoader->load();

        $this->translationLoader = static::getContainer()->get(TranslationLoader::class);
        $this->plugin = new FakePlugin(true, __DIR__);
    }

    public function testValidSnippetMergeWithOnlySameLanguageFiles(): void
    {
        $this->installPlatformSnippets('zh-CN', 'caseSameLanguage/core/zh.json');
        $this->installPluginSnippets('zh-CN', 'caseSameLanguage/plugin/zh.json');

        $actual = $this->createSnippetFinder($this->createKernelWithActivePlugin())->findSnippets('zh-CN');

        $expected = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core',
                    'anotherLabel' => 'core',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin',
                    'anotherLabel' => 'plugin',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core',
                    'uniqueKeyPlugin' => 'plugin',
                    'shouldBeOverwritten' => 'overwritten by plugin',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin',
                ],
            ],
        ];

        static::assertEquals($expected, $actual);
    }

    public function testValidSnippetMergeWithDifferentLanguageFiles(): void
    {
        $this->installPlatformSnippets('zh-CN', 'caseDifferentLanguages/core/zh.json');
        $this->installPluginSnippets('en-GB', 'caseDifferentLanguages/plugin/en.json');

        $actual = $this->createSnippetFinder($this->createKernelWithActivePlugin())->findSnippets('zh-CN');

        $expected = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core',
                    'anotherLabel' => 'core',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core',
                    'shouldBeOverwritten' => 'This time no override',
                    'shouldAlsoBeOverwritten' => 'This time no override either',
                ],
            ],
        ];

        static::assertSame($expected, $actual);
    }

    public function testValidSnippetMergeWithMultipleLanguageFiles(): void
    {
        $this->installPlatformSnippets('zh-CN', 'caseMultipleSameAndDifferentLanguages/core/zh.json');
        $this->installPluginSnippets('zh-CN', 'caseMultipleSameAndDifferentLanguages/plugin/zh.json');
        $this->installPlatformSnippets('en-GB', 'caseMultipleSameAndDifferentLanguages/core/en.json');
        $this->installPluginSnippets('en-GB', 'caseMultipleSameAndDifferentLanguages/plugin/en.json');

        $snippetFinder = $this->createSnippetFinder($this->createKernelWithActivePlugin());

        $actualZh = $snippetFinder->findSnippets('zh-CN');
        $actualEn = $snippetFinder->findSnippets('en-GB');

        $expectedZh = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core zh',
                    'anotherLabel' => 'core zh',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin zh',
                    'anotherLabel' => 'plugin zh',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core zh',
                    'uniqueKeyPlugin' => 'plugin zh',
                    'shouldBeOverwritten' => 'overwritten by plugin zh',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin zh',
                ],
            ],
        ];

        $expectedEn = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core en',
                    'anotherLabel' => 'core en',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin en',
                    'anotherLabel' => 'plugin en',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core en',
                    'uniqueKeyPlugin' => 'plugin en',
                    'shouldBeOverwritten' => 'overwritten by plugin en',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin en',
                ],
            ],
        ];

        static::assertEquals($expectedZh, $actualZh);
        static::assertEquals($expectedEn, $actualEn);
    }

    public function testSnippetFinderSanitizesAppSnippets(): void
    {
        $this->createAppWithMalformedSnippet();
        $snippets = $this->createSnippetFinder(self::getKernel())->findSnippets('en-GB');

        $actualSnippet = $snippets['theme']['label'];
        static::assertSame('<h1>This app</h1> is really <b>safe</b>!)', $actualSnippet);
    }

    private function createAppWithMalformedSnippet(): void
    {
        $context = Context::createDefaultContext();
        static::getContainer()->get('app.repository')->create([
            [
                'id' => $id = Uuid::randomHex(),
                'name' => 'Test app',
                'active' => true,
                'appVersion' => '1.0.0',
                'author' => 'Shopwell AG',
                'label' => [
                    'en-GB' => 'Test App',
                ],
                'path' => 'path',
                'version' => '1.0.0',
                'integration' => [
                    'id' => Uuid::randomHex(),
                    'label' => 'Test app Integration',
                    'accessKey' => Uuid::randomHex(),
                    'secretAccessKey' => Uuid::randomHex(),
                ],
                'aclRole' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test app ACL Role',
                ],
            ],
        ], $context);

        static::getContainer()->get('app_administration_snippet.repository')->create([
            [
                'appId' => $id,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'value' => '{"theme":{"label":"<script>alert(\"xss attack\");</script><h1>This app</h1> is really <b>safe</b>!)"}}',
            ],
        ], $context);
    }

    /**
     * Installs a fixture as the platform snippet file of the given locale, mirroring the layout of the downloaded translation set.
     */
    private function installPlatformSnippets(string $locale, string $fixture): void
    {
        $this->installSnippets(
            Path::join(
                $this->translationLoader->getLocalePath($locale),
                SnippetFileLoader::SCOPE_PLATFORM,
                $locale . '.json'
            ),
            $fixture
        );
    }

    /**
     * Installs a fixture as the snippet file the active plugin ships for the given locale.
     */
    private function installPluginSnippets(string $locale, string $fixture): void
    {
        $this->installSnippets(
            Path::join(
                $this->translationLoader->getLocalePath($locale),
                SnippetFileLoader::SCOPE_PLUGINS,
                $this->translationConfig->getMappedPluginName($this->plugin),
                $locale . '.json'
            ),
            $fixture
        );
    }

    private function installSnippets(string $location, string $fixture): void
    {
        $this->translationFilesystem->write(
            $location,
            $this->localFilesystem->readFile(__DIR__ . '/fixtures/' . $fixture)
        );
    }

    /**
     * The snippet finder discovers plugin snippet files through the kernel, so the fixture plugin is provided by a
     * kernel without any bundles. That keeps the assertions restricted to the installed fixtures.
     */
    private function createKernelWithActivePlugin(): Kernel&Stub
    {
        $pluginLoader = static::createStub(KernelPluginLoader::class);
        $pluginLoader
            ->method('getPluginInstances')
            ->willReturn(new KernelPluginCollection([$this->plugin::class => $this->plugin]));

        $kernel = static::createStub(Kernel::class);
        $kernel
            ->method('getPluginLoader')
            ->willReturn($pluginLoader);
        $kernel
            ->method('getBundles')
            ->willReturn([]);

        return $kernel;
    }

    private function createSnippetFinder(Kernel $kernel): SnippetFinder
    {
        return new SnippetFinder(
            $kernel,
            static::getContainer()->get(Connection::class),
            $this->translationFilesystem,
            $this->translationConfig,
            $this->translationLoader,
            static::getContainer()->get(HtmlSanitizer::class),
            new NullLogger(),
            false,
        );
    }
}

/**
 * @internal
 */
class FakePlugin extends Plugin
{
}
