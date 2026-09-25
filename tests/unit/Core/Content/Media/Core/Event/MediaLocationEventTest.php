<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Core\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Core\Event\MediaLocationEvent;
use Shopwell\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaLocationEvent::class)]
class MediaLocationEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $locations = [
            'foo' => new MediaLocationStruct('foo', 'foo', 'foo', null),
            'bar' => new MediaLocationStruct('bar', 'bar', 'bar', null),
        ];

        $event = new MediaLocationEvent($locations);

        static::assertSame($locations, iterator_to_array($event->getIterator()));
    }
}
