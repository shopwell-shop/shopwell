<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopwell\Core\Content\Media\Event\MediaUploadedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadedEvent::class)]
class MediaUploadedEventTest extends TestCase
{
    public function testInstance(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $mediaUploadEvent = new MediaUploadedEvent(
            $mediaId,
            $context
        );

        static::assertSame('media.uploaded', $mediaUploadEvent->getName());
        static::assertSame($mediaId, $mediaUploadEvent->getMediaId());
        static::assertSame(
            $context,
            $mediaUploadEvent->getContext()
        );
    }

    public function testGetAvailableData(): void
    {
        $eventDataCollection = MediaUploadedEvent::getAvailableData();
        static::assertCount(1, $eventDataCollection->toArray());
        static::assertEquals(
            (new EventDataCollection())->add('mediaId', new ScalarValueType(ScalarValueType::TYPE_STRING)),
            $eventDataCollection
        );
    }

    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new MediaUploadedEvent('media-id', Context::createDefaultContext());

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('mediaId', $flow->data());
        static::assertSame('media-id', $flow->data()['mediaId']);
    }

    public function testGetWebhookPayload(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $mediaUploadEvent = new MediaUploadedEvent(
            $mediaId,
            $context
        );

        static::assertSame(
            [
                'mediaId' => $mediaId,
            ],
            $mediaUploadEvent->getWebhookPayload()
        );
    }

    public function testACL(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $mediaUploadEvent = new MediaUploadedEvent(
            $mediaId,
            $context
        );

        static::assertFalse($mediaUploadEvent->isAllowed('1', new AclPrivilegeCollection(['media:create'])));
        static::assertTrue($mediaUploadEvent->isAllowed('1', new AclPrivilegeCollection(['media:read'])));
    }
}
