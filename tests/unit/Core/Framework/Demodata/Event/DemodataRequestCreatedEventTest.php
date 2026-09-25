<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Demodata\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Demodata\DemodataRequest;
use Shopwell\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DemodataRequestCreatedEvent::class)]
class DemodataRequestCreatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $request = new DemodataRequest();
        $context = Context::createDefaultContext();
        $input = new ArrayInput([]);

        $event = new DemodataRequestCreatedEvent(
            $request,
            $context,
            $input
        );

        static::assertSame($request, $event->getRequest());
        static::assertSame($context, $event->getContext());
        static::assertSame($input, $event->getInput());
    }
}
