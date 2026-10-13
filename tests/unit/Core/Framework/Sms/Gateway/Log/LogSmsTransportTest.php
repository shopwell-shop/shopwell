<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Gateway\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\Log\LogSmsTransport;
use Shopwell\Core\Framework\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LogSmsTransport::class)]
class LogSmsTransportTest extends TestCase
{
    public function testItIsATransportOfTheNotifier(): void
    {
        $transport = $this->createTransport();

        static::assertSame('log', (string) $transport);
        static::assertTrue($transport->supports(new SmsMessage('+8613800000000', 'hello')));
    }

    public function testItLogsTheMessageInsteadOfSendingIt(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('sms.message.logged', ['phone' => '+86138****0000', 'subject' => 'hello']);

        $message = new SmsMessage('+8613800000000', 'hello');

        $sent = $this->createTransport($logger)->send($message);

        static::assertSame($message, $sent->getOriginalMessage());
        static::assertSame('log', $sent->getTransport());
    }

    public function testItKeepsAShortNumberAsItIsInTheLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('sms.message.logged', ['phone' => '123456', 'subject' => 'hello']);

        $this->createTransport($logger)->send(new SmsMessage('123456', 'hello'));
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

        $this->createTransport(dispatcher: $dispatcher)->send(new SmsMessage('+8613800000000', 'hello'));

        static::assertSame([MessageEvent::class, SentMessageEvent::class], $events);
    }

    public function testItWorksWithoutAnEventDispatcher(): void
    {
        static::assertSame('log', $this->createTransport()->send(new SmsMessage('+8613800000000', 'hello'))->getTransport());
    }

    private function createTransport(
        ?LoggerInterface $logger = null,
        ?EventDispatcher $dispatcher = null,
    ): LogSmsTransport {
        return new LogSmsTransport(
            $logger ?? static::createStub(LoggerInterface::class),
            new PhoneNumberNormalizer(new StaticSystemConfigService()),
            $dispatcher,
        );
    }
}
