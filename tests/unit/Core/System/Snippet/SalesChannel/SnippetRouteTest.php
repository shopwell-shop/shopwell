<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\Snippet\SalesChannel\SalesChannelSnippetLoader;
use Shopwell\Core\System\Snippet\SalesChannel\SnippetRoute;
use Shopwell\Core\System\Snippet\SalesChannel\SnippetSetResult;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetRoute::class)]
class SnippetRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }

    public function testLoadParsesTheQueryAndWrapsTheLoaderResult(): void
    {
        $result = $this->createResult();

        $loader = $this->createMock(SalesChannelSnippetLoader::class);
        $loader->expects($this->once())
            ->method('load')
            ->with(['language-one', 'language-two'], ['checkout', 'account'], $this->salesChannelContext)
            ->willReturn([$result]);

        $route = new SnippetRoute($loader, static::createStub(CacheTagCollector::class));

        // comma separated lists with whitespace and empty entries are translated into clean lists
        $request = new Request([
            'languageIds' => ' language-one, language-two,,',
            'prefixes' => 'checkout , account',
        ]);

        $response = $route->load($request, $this->salesChannelContext);

        static::assertSame([$result], $response->getResult()->sets);
        // a single set keeps its own hash as etag
        static::assertSame('"' . $result->hash . '"', $response->getEtag());
    }

    public function testEtagOfMultipleSetsIsCombined(): void
    {
        $first = $this->createResult(hash: 'hash-one');
        $second = $this->createResult(hash: 'hash-two');

        $loader = static::createStub(SalesChannelSnippetLoader::class);
        $loader->method('load')->willReturn([$first, $second]);

        $route = new SnippetRoute($loader, static::createStub(CacheTagCollector::class));

        $response = $route->load(new Request(), $this->salesChannelContext);

        static::assertSame('"' . Hasher::hash('hash-one-hash-two') . '"', $response->getEtag());
    }

    public function testReturnsNotModifiedWhenIfNoneMatchMatchesTheEtag(): void
    {
        $loader = static::createStub(SalesChannelSnippetLoader::class);
        $loader->method('load')->willReturn([$this->createResult()]);

        $route = new SnippetRoute($loader, static::createStub(CacheTagCollector::class));

        $etag = $route->load(new Request(), $this->salesChannelContext)->getEtag();
        static::assertNotNull($etag);

        $request = new Request();
        $request->headers->set('If-None-Match', $etag);

        $response = $route->load($request, $this->salesChannelContext);

        static::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        static::assertSame($etag, $response->getEtag());
    }

    public function testAddsACacheTagPerSnippetSet(): void
    {
        $first = $this->createResult(snippetSetId: 'set-one');
        $second = $this->createResult(snippetSetId: 'set-two');

        $loader = static::createStub(SalesChannelSnippetLoader::class);
        $loader->method('load')->willReturn([$first, $second]);

        $collectedTags = [];
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->exactly(2))
            ->method('addTag')
            ->willReturnCallback(function (string ...$tags) use (&$collectedTags): void {
                $collectedTags = [...$collectedTags, ...$tags];
            });

        $route = new SnippetRoute($loader, $cacheTagCollector);

        $route->load(new Request(), $this->salesChannelContext);

        static::assertSame([Translator::tag('set-one'), Translator::tag('set-two')], $collectedTags);
    }

    public function testGetDecoratedThrows(): void
    {
        $route = new SnippetRoute(
            static::createStub(SalesChannelSnippetLoader::class),
            static::createStub(CacheTagCollector::class)
        );

        $this->expectExceptionObject(new DecorationPatternException(SnippetRoute::class));

        $route->getDecorated();
    }

    private function createResult(string $hash = 'test-hash', string $snippetSetId = 'test-set-id'): SnippetSetResult
    {
        return new SnippetSetResult(
            languageId: Uuid::randomHex(),
            locale: 'pl-PL',
            fallbackLocale: 'pl',
            snippetSetId: $snippetSetId,
            hash: $hash,
            snippets: ['checkout.cart.title' => 'Koszyk'],
        );
    }
}
