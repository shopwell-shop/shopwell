<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ContentSystem\Layout\Preset;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopwell\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopwell\Core\Framework\ContentSystem\ContentPipeline;
use Shopwell\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\ContentSystem\PlaceholderValues;
use Shopwell\Core\Framework\ContentSystem\RenderableLayout;
use Shopwell\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopwell\Core\Framework\ContentSystem\RenderingMode;
use Shopwell\Core\Framework\ContentSystem\RenderingSpecification;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class ShippedPresetsTest extends TestCase
{
    use KernelTestBehaviour;

    #[TestDox('loads every shipped preset without logging warnings, and each one is a valid layout that renders every element')]
    public function testEveryShippedPresetIsAValidLayoutThatRenders(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $presets = (new ContentSystemLayoutPresetRegistry(
            [static::getContainer()->get(YamlLayoutPresetLoader::class)],
            static::getContainer()->get(LayoutPresetPayloadCompiler::class),
            static::getContainer()->get(ContentSystemLayoutPresetValidator::class),
            $logger,
        ))->all();

        static::assertNotSame([], $presets);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        foreach ($presets as $id => $preset) {
            $elements = static::getContainer()->get(DraftLayoutDecoder::class)->decode($preset->payload);
            $violations = static::getContainer()->get(DraftLayoutChecker::class)->check($elements);

            static::assertCount(0, $violations, \sprintf('The "%s" preset must be a valid layout: %s', $id, $violations));

            $result = static::getContainer()->get(ContentPipeline::class)->load(
                RenderableLayout::create(LayoutReference::create(Uuid::randomHex(), $id, null), $elements),
                new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
                new RenderingCacheContext(),
                RenderingMode::FULL,
                false,
                $context,
            );

            static::assertSame(
                $this->payloadIds($preset->payload),
                $this->renderedIds($result->tree),
                \sprintf('The "%s" preset must render every element.', $id),
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $payload
     *
     * @return list<string>
     */
    private function payloadIds(array $payload): array
    {
        $ids = [];

        foreach ($payload as $element) {
            $ids[] = $element['id'];

            foreach ($element['slots'] ?? [] as $children) {
                array_push($ids, ...$this->payloadIds($children));
            }
        }

        return $ids;
    }

    /**
     * @param list<RenderedElement> $tree
     *
     * @return list<string>
     */
    private function renderedIds(array $tree): array
    {
        $ids = [];

        foreach ($tree as $element) {
            $ids[] = $element->id;

            foreach ($element->slots as $children) {
                array_push($ids, ...$this->renderedIds($children));
            }
        }

        return $ids;
    }
}
