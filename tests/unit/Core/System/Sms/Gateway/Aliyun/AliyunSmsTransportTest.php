<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponse;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponseBody;
use Darabonba\OpenApi\Exceptions\ClientException;
use Darabonba\OpenApi\Exceptions\ServerException;
use Darabonba\OpenApi\Exceptions\ThrottlingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunSmsOptions;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunSmsTransport;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\SmsUnrecoverableException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunSmsTransport::class)]
class AliyunSmsTransportTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $transport = $this->createTransport();

        static::assertSame('aliyun', (string) $transport);
        static::assertTrue($transport->supports(new SmsMessage('+8613800000000', 'hello')));
        static::assertFalse($transport->supports(static::createStub(MessageInterface::class)));
    }

    public function testItSubmitsTheTemplateAndTheTrimmedNumber(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('sendSms')
            ->with(static::callback(static function (SendSmsRequest $request): bool {
                // the api takes a domestic number without the country code prefix
                return $request->phoneNumbers === '8613800000000'
                    && $request->signName === 'Shopwell'
                    && $request->templateCode === 'SMS_123456789'
                    && $request->templateParam === '{"code":"1234"}'
                    && $request->outId === 'out-1';
            }))
            ->willReturn($this->response(['bizId' => '9006198501199', 'code' => 'OK', 'message' => 'OK', 'requestId' => 'req-1']));

        $sent = $this->createTransport($client)->send($this->message());

        static::assertSame('aliyun', $sent->getTransport());
        static::assertSame('9006198501199', $sent->getMessageId());
        static::assertSame('9006198501199', $sent->getInfo('bizId'));
        static::assertSame('req-1', $sent->getInfo('requestId'));
    }

    public function testItFallsBackToTheSignatureTheAccountWasConfiguredWith(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('sendSms')
            ->with(static::callback(static fn (SendSmsRequest $request): bool => $request->signName === 'Configured'))
            ->willReturn($this->response(['code' => 'OK', 'message' => 'OK']));

        $message = new SmsMessage('+8613800000000', 'hello', options: new AliyunSmsOptions('SMS_1', null));
        $transport = $this->createTransport($client, 'Configured');

        static::assertSame('aliyun', $transport->send($message)->getTransport());
    }

    public function testAnAcceptedRequestWithoutAReceiptIsStillSent(): void
    {
        $sent = $this->createTransport($this->clientReturning(['code' => 'OK', 'message' => 'OK']))
            ->send($this->message());

        static::assertNull($sent->getMessageId());
        static::assertNull($sent->getInfo('bizId'));
    }

    public function testAFailureAtTheSideOfAliyunIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'isp.SYSTEM_ERROR', 'busy'));

        $this->createTransport($this->clientReturning(['code' => 'isp.SYSTEM_ERROR', 'message' => 'busy']))
            ->send($this->message());
    }

    public function testAThrottledRequestIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'isv.BUSINESS_LIMIT_CONTROL', 'triggered'));

        $this->createTransport($this->clientReturning(['code' => 'isv.BUSINESS_LIMIT_CONTROL', 'message' => 'triggered']))
            ->send($this->message());
    }

    public function testATemplateTheProviderDoesNotKnowIsFinal(): void
    {
        $this->expectExceptionObject(SmsUnrecoverableException::providerRejected('aliyun', 'isv.SMS_TEMPLATE_ILLEGAL', 'not approved'));

        $this->createTransport($this->clientReturning(['code' => 'isv.SMS_TEMPLATE_ILLEGAL', 'message' => 'not approved']))
            ->send($this->message());
    }

    public function testAnAcceptedRequestWithoutACodeIsFinal(): void
    {
        $this->expectExceptionObject(SmsUnrecoverableException::providerRejected('aliyun', '', ''));

        $this->createTransport($this->clientReturning([]))->send($this->message());
    }

    public function testAStatusThatAsksForAnotherTryIsNotACodeOfTheApi(): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('sendSms')->willThrowException(new ThrottlingException($this->failure('Throttling', 'busy', 429)));

        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'Throttling', 'busy'));

        $this->createTransport($client)->send($this->message());
    }

    public function testAnErrorAtTheSideOfAliyunAsksForAnotherTry(): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('sendSms')->willThrowException(new ServerException($this->failure('InternalError', 'boom', 500)));

        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'InternalError', 'boom'));

        $this->createTransport($client)->send($this->message());
    }

    public function testACredentialAliyunRefusedIsFinal(): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('sendSms')->willThrowException(new ClientException($this->failure('InvalidAccessKeyId.NotFound', 'the key is unknown', 400)));

        $this->expectExceptionObject(SmsUnrecoverableException::providerRejected('aliyun', 'InvalidAccessKeyId.NotFound', 'the key is unknown'));

        $this->createTransport($client)->send($this->message());
    }

    public function testAMessageWithoutOptionsOfAProviderIsRefused(): void
    {
        $this->expectExceptionObject(SmsException::templateModeRequired('aliyun'));

        $this->createTransport()->send(new SmsMessage('+8613800000000', 'plain text'));
    }

    public function testAMessageWithTheOptionsOfAFreeTextProviderIsRefused(): void
    {
        $message = new SmsMessage('+8613800000000', 'plain text', options: static::createStub(MessageOptionsInterface::class));

        $this->expectExceptionObject(SmsException::templateModeRequired('aliyun'));

        $this->createTransport()->send($message);
    }

    public function testAnEmptyTemplateCodeIsRefused(): void
    {
        $this->expectExceptionObject(SmsException::templateModeRequired('aliyun'));

        $this->createTransport()->send(new SmsMessage('+8613800000000', 'hello', options: new AliyunSmsOptions('', 'Shopwell')));
    }

    public function testAGatewayWithoutASignatureCannotSend(): void
    {
        $this->expectExceptionObject(SmsUnrecoverableException::signNameMissing('aliyun'));

        $this->createTransport()->send(new SmsMessage('+8613800000000', 'hello', options: new AliyunSmsOptions('SMS_1', '')));
    }

    public function testAMessageWithoutANumberIsRefused(): void
    {
        $message = static::createStub(MessageInterface::class);
        $message->method('getOptions')->willReturn(new AliyunSmsOptions('SMS_1', 'Shopwell'));
        $message->method('getRecipientId')->willReturn(null);

        $this->expectExceptionObject(SmsException::invalidPhoneNumber(''));

        $this->createTransport()->send($message);
    }

    private function createTransport(?Dysmsapi $client = null, ?string $defaultSignName = null): AliyunSmsTransport
    {
        return new AliyunSmsTransport($client ?? $this->createMock(Dysmsapi::class), $defaultSignName);
    }

    private function message(): SmsMessage
    {
        return new SmsMessage(
            '+8613800000000',
            'hello',
            options: new AliyunSmsOptions('SMS_123456789', 'Shopwell', ['code' => '1234'], 'out-1')
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function clientReturning(array $body): Dysmsapi
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('sendSms')->willReturn($this->response($body));

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function response(array $body): SendSmsResponse
    {
        return new SendSmsResponse([
            'headers' => [],
            'statusCode' => 200,
            'body' => new SendSmsResponseBody($body),
        ]);
    }

    /**
     * The exception of the sdk is built from a map that has to carry every key, the request id and
     * the detail of a denied access included, which is why the failure is assembled here.
     *
     * @return array<string, mixed>
     */
    private function failure(string $code, string $message, int $statusCode): array
    {
        return [
            'statusCode' => $statusCode,
            'code' => $code,
            'message' => $message,
            'description' => '',
            'requestId' => 'req-1',
            'accessDeniedDetail' => null,
            'data' => [],
            'retryAfter' => 0,
        ];
    }
}
