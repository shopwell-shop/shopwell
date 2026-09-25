<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopwell\Core\Content\Product\Extension\ResolveListingIdsExtension;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Tests\Examples\ResolveListingIdsExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ResolveListingIdsExtension::class)]
class ResolveListingIdsExtensionTest extends TestCase
{
    public function testResolveListingIdsExtension(): void
    {
        $responseBody = json_encode(['ids' => ['plugin-id'], 'total' => 1], \JSON_THROW_ON_ERROR);

        $mockHandler = new MockHandler([new Response(200, [], $responseBody)]);
        $handlerStack = HandlerStack::create($mockHandler);

        $history = [];
        $handlerStack->push(Middleware::history($history));

        $client = new Client(['handler' => $handlerStack]);

        $example = new ResolveListingIdsExample($client);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ResolveListingIdsExtension::NAME . '.pre', $example);

        $extension = new ResolveListingIdsExtension(
            new Criteria(),
            static::createStub(SalesChannelContext::class)
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ResolveListingIdsExtension::NAME,
            extension: $extension,
            function: static function () {
                return IdSearchResult::fromIds(['core-id'], new Criteria(), Context::createDefaultContext());
            }
        );

        static::assertInstanceOf(IdSearchResult::class, $result);
        static::assertSame(['plugin-id'], $result->getIds());
        static::assertIsArray($history);
        static::assertCount(1, $history);

        $request = $history[0]['request'];
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
    }
}
