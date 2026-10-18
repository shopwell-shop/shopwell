<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\System\Sms\Transport\SmsTransports;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTransports::class)]
class SmsTransportsTest extends TestCase
{
    public function testItIsATransportOfTheNotifier(): void
    {
        $transports = new SmsTransports(static::createStub(SmsTransportLoader::class));

        static::assertSame('sms-transports', (string) $transports);
        static::assertTrue($transports->supports(new SmsMessage('+8613800000000', 'hello')));
        static::assertFalse($transports->supports(new ChatMessage('hello')));
    }

    public function testItSendsOverTheGatewayTheMessageNames(): void
    {
        $message = (new SmsMessage('+8613800000000', 'hello'))->transport('gateway-id');
        $expected = new SentMessage($message, 'aliyun');

        $transport = static::createStub(TransportInterface::class);
        $transport->method('send')->willReturn($expected);

        $loader = $this->createMock(SmsTransportLoader::class);
        $loader->expects($this->once())
            ->method('fromGatewayId')
            ->with('gateway-id')
            ->willReturn($transport);

        static::assertSame($expected, (new SmsTransports($loader))->send($message));
    }

    public function testItSendsOverTheNullGatewayWhenTheMessageNamesNone(): void
    {
        $message = new SmsMessage('+8613800000000', 'hello');

        $transport = static::createStub(TransportInterface::class);
        $transport->method('send')->willReturnCallback(
            static fn (MessageInterface $sent): SentMessage => new SentMessage($sent, 'null')
        );

        $loader = $this->createMock(SmsTransportLoader::class);
        $loader->expects($this->once())
            ->method('fromGatewayId')
            ->with(SmsGatewayResolverInterface::GATEWAY_NULL)
            ->willReturn($transport);

        static::assertNotNull((new SmsTransports($loader))->send($message));
    }

    public function testItBuildsTheTransportOfAGatewayOnlyOnce(): void
    {
        $transport = static::createStub(TransportInterface::class);
        $transport->method('send')->willReturnCallback(
            static fn (MessageInterface $sent): SentMessage => new SentMessage($sent, 'aliyun')
        );

        $loader = $this->createMock(SmsTransportLoader::class);
        $loader->expects($this->once())
            ->method('fromGatewayId')
            ->with('gateway-id')
            ->willReturn($transport);

        $transports = new SmsTransports($loader);

        $transports->send((new SmsMessage('+8613800000000', 'hello'))->transport('gateway-id'));
        $transports->send((new SmsMessage('+8613800000001', 'again'))->transport('gateway-id'));
    }
}
