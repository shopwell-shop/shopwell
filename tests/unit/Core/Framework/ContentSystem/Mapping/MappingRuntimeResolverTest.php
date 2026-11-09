<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingRejection;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingRuntimeResolver;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopwell\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingRuntimeResolver::class)]
class MappingRuntimeResolverTest extends TestCase
{
    private AbstractContentSystemMappingCandidateRegistry&MockObject $registry;

    private MappingCandidate $candidate;

    private MappingSourceResolutionContext $context;

    private RenderingCacheContext $cacheContext;

    private MappingRuntimeResolver $resolver;

    protected function setUp(): void
    {
        $this->candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string');
        $this->registry = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $types = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $types->method('has')->willReturn(true);
        $types->method('get')->willReturn(ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
            ->primitive('text', 'string', mappable: true, inlineMappable: true)
            ->build());
        $this->cacheContext = new RenderingCacheContext();
        $this->context = new MappingSourceResolutionContext(
            new StoredElement('element-1', 'Sw:Content:Text'),
            ['product' => 'root-data'],
            ['selectedName' => 'Shirt'],
            $this->cacheContext,
        );
        $this->resolver = new MappingRuntimeResolver($this->registry, new StoredMappingInspector(
            $types,
            $this->registry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        ));
    }

    #[DataProvider('mappingKinds')]
    public function testUnavailableMappingsStillRecordMetadataDependencies(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([]);
        $this->registry->expects($this->never())->method('resolveAdmittedSource');
        $this->registry->expects($this->once())->method('cacheTagsForMapping')->with('product', 'product.name')->willReturn(['provider-metadata']);

        static::assertSame(MappingRejection::Unavailable, $this->resolve($inline));
        static::assertSame(['provider-metadata'], $this->cacheContext->getTags());
    }

    #[DataProvider('mappingKinds')]
    public function testAdmittedResolutionPreservesTheProviderResultAndCacheDependencies(bool $inline): void
    {
        $expected = MappingSourceResolution::resolved('Shirt');
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveAdmittedSource')->willReturnCallback(
            function (string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context) use ($expected): MappingSourceResolution {
                static::assertSame('product', $rootSource);
                static::assertSame($this->candidate->source, $source);
                static::assertSame($this->context, $context);
                static::assertSame($this->cacheContext, $context->cacheContext);
                $context->cacheContext->addTags(['provider-data']);

                return $expected;
            },
        );

        static::assertSame($expected, $this->resolve($inline));
        static::assertSame(['provider-data'], $this->cacheContext->getTags());
    }

    #[DataProvider('mappingKinds')]
    public function testMissingDataRemainsEligibleForMediumSpecificFallback(bool $inline): void
    {
        $expected = MappingSourceResolution::noValue();
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveAdmittedSource')->willReturn($expected);

        static::assertSame($expected, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testUnavailableCandidateIsRejectedBeforeProviderDispatch(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([]);
        $this->registry->expects($this->never())->method('resolveAdmittedSource');

        static::assertSame(MappingRejection::Unavailable, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testIncompatibleValueIsRejected(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveAdmittedSource')->willReturn(MappingSourceResolution::resolved(42));

        static::assertSame(MappingRejection::Incompatible, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testExplicitNullRemainsAResolvedValue(bool $inline): void
    {
        $expected = MappingSourceResolution::resolved(null);
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveAdmittedSource')->willReturn($expected);

        static::assertSame($expected, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testProviderErrorsPropagate(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveAdmittedSource')->willThrowException(new \TypeError('Provider failed'));

        $this->expectException(\TypeError::class);

        $this->resolve($inline);
    }

    public function testStoredCardinalityIsAdmittedBeforeProviderDispatch(): void
    {
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->never())->method('resolveAdmittedSource');

        static::assertSame(MappingRejection::Unavailable, $this->resolver->resolveProperty(
            'product',
            'text',
            ContextType::Collection,
            $this->candidate->source,
            $this->context,
        ));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function mappingKinds(): iterable
    {
        yield 'whole-field' => [false];
        yield 'inline' => [true];
    }

    private function resolve(bool $inline): MappingSourceResolution|MappingRejection
    {
        return $inline
            ? $this->resolver->resolveInline('product', $this->candidate->path, $this->context)
            : $this->resolver->resolveProperty('product', 'text', ContextType::Single, $this->candidate->source, $this->context);
    }
}
