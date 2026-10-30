<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Loader;

use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\App\Feature\AppFeature;
use Shopwell\Core\Framework\App\Feature\AppFeatureStorage;
use Shopwell\Core\Framework\App\Feature\TranslatedString;
use Shopwell\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 *
 * Exercises the shared load flow through AppMcpPromptLoader, the lightest concrete subclass.
 */
#[Package('framework')]
#[CoversClass(AbstractAppMcpLoader::class)]
class AbstractAppMcpLoaderTest extends TestCase
{
    private AppFeatureStorage&Stub $storage;

    private AppMcpPromptLoader $loader;

    protected function setUp(): void
    {
        $this->storage = static::createStub(AppFeatureStorage::class);
        $localeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $localeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');
        $this->loader = new AppMcpPromptLoader(
            $this->storage,
            static::createStub(AppMcpCapabilityExecutor::class),
            $localeProvider,
            new NullLogger(),
        );
    }

    public function testNoActiveAppsRegisterNoPrompts(): void
    {
        $this->storage->method('forActiveApps')->willReturn([]);

        static::assertSame([], $this->registeredPrompts());
    }

    public function testAPromptWithAnEmptyLabelAndDescriptionIsDescribedByItsName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature(new McpPromptConfig('order-context', 'https://app.example.com/mcp/prompt/order-context', new TranslatedString(['en-GB' => '']), new TranslatedString([]))),
        ]);

        $prompt = $this->registeredPrompts()[0];

        static::assertNull($prompt->title);
        static::assertSame('my-app-order-context', $prompt->description);
    }

    public function testAPromptNamedWithTheShopwellPrefixIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature(new McpPromptConfig('context', 'https://app.example.com/mcp/prompt/context', new TranslatedString([]), new TranslatedString([])), 'shopwell'),
        ]);

        static::assertSame([], $this->registeredPrompts());
    }

    /**
     * @return list<Prompt>
     */
    private function registeredPrompts(): array
    {
        $prompts = [];

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerPrompt')->willReturnCallback(function (Prompt $prompt) use (&$prompts): PromptReference {
            $prompts[] = $prompt;

            return static::createStub(PromptReference::class);
        });

        $this->loader->load($registry);

        return $prompts;
    }

    /**
     * @return AppFeature<McpPromptConfig>
     */
    private function feature(McpPromptConfig $config, string $appName = 'my-app'): AppFeature
    {
        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, '0.0.0', true, new \DateTimeImmutable(), $config);
    }
}
