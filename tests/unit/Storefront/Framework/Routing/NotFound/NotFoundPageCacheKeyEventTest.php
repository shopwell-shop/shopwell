<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Routing\NotFound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NotFoundPageCacheKeyEvent::class)]
class NotFoundPageCacheKeyEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $event = new NotFoundPageCacheKeyEvent('test', $request, $context);

        static::assertSame('test', $event->getKey());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($request, $event->getRequest());

        $event->setKey('test2');
        static::assertSame('test2', $event->getKey());
    }
}
