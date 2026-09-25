<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\MessageQueueException;
use Shopwell\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopwell\Core\Framework\MessageQueue\Subscriber\MessageQueueSizeRestrictListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MessageQueueSizeRestrictListener::class)]
class MessageQueueSizeRestrictListenerTest extends TestCase
{
    public function testSmallMessageSyncTransportNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true, 1024);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, ['test' => static::createStub(SyncTransport::class)]);

        $listener($event);
    }

    public function testBigMessageSyncTransportNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $maxMessageSizeKiB = 1024;

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true, $maxMessageSizeKiB);

        $message = new \stdClass();
        $message->a = str_repeat('a', $maxMessageSizeKiB * 1024);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, ['test' => static::createStub(SyncTransport::class)]);

        $listener($event);
    }

    public function testSmallMessageAsyncTransportNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true, 1024);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testMessageNoMessageSizeLimitNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true, 0);

        $message = new \stdClass();
        $message->a = str_repeat('a', 1024 * 1024 * 2);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testBigMessageAsyncTransportException(): void
    {
        $serializer = new Serializer();

        $maxMessageSizeKiB = 1024;

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true, $maxMessageSizeKiB);

        $message = new \stdClass();
        $message->a = str_repeat('a', $maxMessageSizeKiB * 1024);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, []);

        // 0.0859375 is the overhead of the serialization
        $size = $maxMessageSizeKiB + 0.0859375;
        $this->expectExceptionObject(MessageQueueException::maxQueueMessageSizeExceeded('stdClass', $size, $maxMessageSizeKiB));

        $listener($event);
    }

    public function testSmallMessageAsyncTransportNoExceptionWithDisabledEnforceMessageSize(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), false, 1024);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testBigMessageAsyncTransportNoExceptionWithDisabledEnforceMessageSize(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $maxMessageSizeKiB = 1024;

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), false, $maxMessageSizeKiB);

        $message = new \stdClass();
        $message->a = str_repeat('a', $maxMessageSizeKiB * 1024);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }
}
