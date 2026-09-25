<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Update\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Event\UpdateEvent;
use Shopwell\Core\Framework\Update\Event\UpdatePreFinishEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdatePreFinishEvent::class)]
#[CoversClass(UpdateEvent::class)]
class UpdatePreFinishEventTest extends TestCase
{
    public function testGetters(): void
    {
        $context = Context::createDefaultContext();
        $event = new UpdatePreFinishEvent($context, 'oldVersion', 'newVersion');

        static::assertSame('oldVersion', $event->getOldVersion());
        static::assertSame('newVersion', $event->getNewVersion());
        static::assertSame($context, $event->getContext());
    }
}
