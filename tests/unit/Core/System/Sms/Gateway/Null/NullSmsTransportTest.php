<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Null;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Null\NullSmsTransport;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NullSmsTransport::class)]
class NullSmsTransportTest extends TestCase
{
    public function testItIsATransportOfTheNotifier(): void
    {
        $transport = new NullSmsTransport();

        static::assertSame('null', (string) $transport);
        static::assertTrue($transport->supports(new SmsMessage('+8613800000000', 'hello')));
    }

    public function testItReportsASentMessageOfItsOwn(): void
    {
        $message = new SmsMessage('+8613800000000', 'hello');

        $sent = (new NullSmsTransport())->send($message);

        static::assertSame($message, $sent->getOriginalMessage());
        static::assertSame('null', $sent->getTransport());
        static::assertNull($sent->getMessageId());
    }

    public function testItDispatchesTheEventsOfARealSend(): void
    {
        $dispatcher = new EventDispatcher();

        $events = [];
        $dispatcher->addListener(MessageEvent::class, static function () use (&$events): void {
            $events[] = MessageEvent::class;
        });
        $dispatcher->addListener(SentMessageEvent::class, static function () use (&$events): void {
            $events[] = SentMessageEvent::class;
        });

        (new NullSmsTransport($dispatcher))->send(new SmsMessage('+8613800000000', 'hello'));

        static::assertSame([MessageEvent::class, SentMessageEvent::class], $events);
    }
}
