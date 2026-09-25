<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Core\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateThumbnailPathEvent::class)]
class UpdateThumbnailPathEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $event = new UpdateThumbnailPathEvent(['foo', 'bar']);

        static::assertSame(['foo', 'bar'], iterator_to_array($event->getIterator()));
    }
}
