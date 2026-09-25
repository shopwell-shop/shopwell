<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Plugin\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Context\ActivateContext;
use Shopwell\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Shopwell\Core\Framework\Plugin\PluginEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginPostDeactivationFailedEvent::class)]
class PluginPostDeactivationFailedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $activateContext = static::createStub(ActivateContext::class);
        $exception = new \Exception('failed');
        $event = new PluginPostDeactivationFailedEvent(
            new PluginEntity(),
            $activateContext,
            $exception
        );
        static::assertSame($activateContext, $event->getContext());
        static::assertSame($exception, $event->getException());
    }
}
