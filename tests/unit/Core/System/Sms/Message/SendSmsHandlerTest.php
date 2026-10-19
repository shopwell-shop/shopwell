<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Message\SendLowPrioritySmsMessage;
use Shopwell\Core\System\Sms\Message\SendSmsHandler;
use Shopwell\Core\System\Sms\Message\SendSmsMessage;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\SmsUnrecoverableException;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\System\Sms\Transport\SmsTransports;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SendSmsHandler::class)]
class SendSmsHandlerTest extends TestCase
{
    public function testItSendsTheMessageOfAQueuedNotification(): void
    {
        $message = (new SmsMessage('+8613800000000', 'hello'))->transport('gateway-id');

        [$handler, $sent] = $this->createHandler($message);

        static::assertSame($sent, $handler->__invoke(new SendSmsMessage($message)));
    }

    public function testItSendsTheMessageOfAQueuedMarketingMessage(): void
    {
        $message = (new SmsMessage('+8613800000000', 'sale'))->transport('gateway-id');

        [$handler, $sent] = $this->createHandler($message);

        static::assertSame($sent, $handler->__invoke(new SendLowPrioritySmsMessage($message)));
    }

    public function testAProviderThatRefusedTheMessageForGoodStopsTheQueue(): void
    {
        $message = (new SmsMessage('+8613800000000', 'hello'))->transport('gateway-id');

        $refusal = SmsUnrecoverableException::providerRejected('aliyun', 'isv.SMS_TEMPLATE_ILLEGAL', 'not approved');

        $transport = static::createStub(TransportInterface::class);
        $transport->method('send')->willThrowException($refusal);

        $loader = $this->createMock(SmsTransportLoader::class);
        $loader->expects($this->once())
            ->method('fromGatewayId')
            ->with('gateway-id')
            ->willReturn($transport);

        // the handler turns the refusal into an unrecoverable failure carrying its message
        $this->expectExceptionObject(new UnrecoverableMessageHandlingException($refusal->getMessage(), 0, $refusal));

        (new SendSmsHandler(new SmsTransports($loader)))->__invoke(new SendSmsMessage($message));
    }

    public function testAFailureThatCanPassLaterIsLeftToTheRetriesOfTheQueue(): void
    {
        $message = (new SmsMessage('+8613800000000', 'hello'))->transport('gateway-id');

        $transport = static::createStub(TransportInterface::class);
        $transport->method('send')->willThrowException(SmsException::providerUnavailable('aliyun', 'isp.SYSTEM_ERROR', 'busy'));

        $loader = $this->createMock(SmsTransportLoader::class);
        $loader->expects($this->once())
            ->method('fromGatewayId')
            ->with('gateway-id')
            ->willReturn($transport);

        $this->expectException(SmsException::class);

        (new SendSmsHandler(new SmsTransports($loader)))->__invoke(new SendSmsMessage($message));
    }

    /**
     * @return array{0: SendSmsHandler, 1: SentMessage}
     */
    private function createHandler(SmsMessage $message): array
    {
        $sent = new SentMessage($message, 'aliyun');

        $transport = static::createStub(TransportInterface::class);
        $transport->method('send')->willReturn($sent);

        $loader = $this->createMock(SmsTransportLoader::class);
        $loader->expects($this->once())
            ->method('fromGatewayId')
            ->with('gateway-id')
            ->willReturn($transport);

        return [new SendSmsHandler(new SmsTransports($loader)), $sent];
    }
}
