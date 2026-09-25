<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Routing\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Framework\Routing\Exception\ErrorRedirectRequestEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ErrorRedirectRequestEvent::class)]
class ErrorRedirectRequestEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();
        $exception = new \Exception();

        $event = new ErrorRedirectRequestEvent($request, $exception, $context);

        static::assertSame($context, $event->getContext());
        static::assertSame($exception, $event->getException());
        static::assertSame($request, $event->getRequest());
    }
}
