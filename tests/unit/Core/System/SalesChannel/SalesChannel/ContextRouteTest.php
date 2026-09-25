<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextRoute::class)]
class ContextRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(ContextRoute::class));

        (new ContextRoute())->getDecorated();
    }

    public function testLoadReturnsContextTokenHeader(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'test-token');

        $response = (new ContextRoute())->load($context);

        static::assertSame($context, $response->getContext());
        static::assertSame('test-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
