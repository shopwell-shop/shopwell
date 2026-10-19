<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\TencentCloud;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudSmsOptions;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudSmsTransport;
use Shopwell\Core\System\Sms\SmsException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\Models\SendSmsResponse;
use TencentCloud\Sms\V20210111\Models\SendStatus;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * The actions of the sdk are reached through its __call, which is what the mock of the client
 * intercepts - configuring SendSms itself is not possible because the method does not exist.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(TencentCloudSmsTransport::class)]
class TencentCloudSmsTransportTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $transport = $this->createTransport();

        static::assertSame('tencentcloud', (string) $transport);
        static::assertTrue($transport->supports(new SmsMessage('+8613800000000', 'hello')));
        static::assertFalse($transport->supports(static::createStub(MessageInterface::class)));
    }

    public function testItSubmitsTheTemplateAndTheParametersInTheOrderOfTheirNames(): void
    {
        $client = $this->createMock(SmsClient::class);
        $client->expects($this->once())
            ->method('__call')
            ->with(
                'SendSms',
                static::callback(static function (array $arguments): bool {
                    $request = $arguments[0] ?? null;

                    return $request instanceof SendSmsRequest
                        // the api takes the e.164 form including the plus
                        && ['+8613800000000'] === $request->PhoneNumberSet
                        && $request->SignName === 'Shopwell'
                        && $request->TemplateId === '1234567'
                        // named 2 and 1 at the call site, submitted by their number
                        && ['first', 'second'] === $request->TemplateParamSet
                        && $request->SmsSdkAppId === '1400006666'
                        && $request->SessionContext === 'session-1';
                })
            )
            ->willReturn($this->response($this->sendStatus('Ok')));

        $sent = $this->createTransport($client)->send($this->message());

        static::assertSame('tencentcloud', $sent->getTransport());
        static::assertSame('serial-1', $sent->getMessageId());
        static::assertSame('serial-1', $sent->getInfo('serialNo'));
        static::assertSame('req-1', $sent->getInfo('requestId'));
    }

    public function testItFallsBackToTheSignatureTheAccountWasConfiguredWith(): void
    {
        $client = $this->createMock(SmsClient::class);
        $client->expects($this->once())
            ->method('__call')
            ->with(
                'SendSms',
                static::callback(static function (array $arguments): bool {
                    $request = $arguments[0] ?? null;

                    return $request instanceof SendSmsRequest && $request->SignName === 'Configured';
                })
            )
            ->willReturn($this->response($this->sendStatus('Ok')));

        $message = new SmsMessage('+8613800000000', 'hello', options: new TencentCloudSmsOptions('1234567', null));
        $transport = $this->createTransport($client, 'Configured');

        static::assertSame('tencentcloud', $transport->send($message)->getTransport());
    }

    public function testASuccessWithoutAReceiptIsStillSent(): void
    {
        $sent = $this->createTransport($this->clientReturning($this->response($this->sendStatus('Ok', ''))))
            ->send($this->message());

        static::assertNull($sent->getMessageId());
    }

    public function testAnErrorAtTheSideOfTencentCloudIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable('tencentcloud', 'InternalError.SystemError', 'boom'));

        $this->createTransport($this->clientReturning($this->response($this->sendStatus('InternalError.SystemError', 'serial-1', 'boom'))))
            ->send($this->message());
    }

    public function testATemplateTheProviderDoesNotKnowIsFinal(): void
    {
        $this->expectExceptionObject(SmsException::providerRejected('tencentcloud', 'FailedOperation.TemplateIncorrectOrUnapproved', 'not approved'));

        $this->createTransport($this->clientReturning($this->response($this->sendStatus('FailedOperation.TemplateIncorrectOrUnapproved', 'serial-1', 'not approved'))))
            ->send($this->message());
    }

    public function testAnAnswerWithoutAStatusForTheNumberIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable(
            'tencentcloud',
            'MissingSendStatus',
            'The response of tencent cloud carried no status for the number.'
        ));

        $this->createTransport($this->clientReturning($this->response()))->send($this->message());
    }

    public function testARejectionOfTheApiIsFinal(): void
    {
        $this->expectExceptionObject(SmsException::providerRejected('tencentcloud', 'AuthFailure.SignatureFailure', 'the signature is wrong'));

        $this->createTransport($this->clientThrowing(new TencentCloudSDKException('AuthFailure.SignatureFailure', 'the signature is wrong', 'req-1')))
            ->send($this->message());
    }

    public function testARejectionTheApiMarksAsTransientIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable('tencentcloud', 'RequestLimitExceeded', 'too many requests'));

        $this->createTransport($this->clientThrowing(new TencentCloudSDKException('RequestLimitExceeded', 'too many requests', 'req-1')))
            ->send($this->message());
    }

    public function testARequestThatNeverReachedTheApiIsWorthAnotherTry(): void
    {
        // the sdk wraps a failure of the transport itself without a code
        $this->expectExceptionObject(SmsException::providerUnavailable('tencentcloud', 'TransportError', 'Connection timed out'));

        $this->createTransport($this->clientThrowing(new TencentCloudSDKException('', 'Connection timed out')))
            ->send($this->message());
    }

    public function testAMessageWithTheOptionsOfAFreeTextProviderIsRefused(): void
    {
        $message = new SmsMessage('+8613800000000', 'plain text', options: static::createStub(MessageOptionsInterface::class));

        $this->expectExceptionObject(SmsException::templateModeRequired('tencentcloud'));

        $this->createTransport()->send($message);
    }

    public function testAnEmptyTemplateCodeIsRefused(): void
    {
        $this->expectExceptionObject(SmsException::templateModeRequired('tencentcloud'));

        $this->createTransport()->send(new SmsMessage('+8613800000000', 'hello', options: new TencentCloudSmsOptions('', 'Shopwell')));
    }

    public function testAGatewayWithoutASignatureCannotSend(): void
    {
        $this->expectExceptionObject(SmsException::signNameMissing('tencentcloud'));

        $this->createTransport()->send(new SmsMessage('+8613800000000', 'hello', options: new TencentCloudSmsOptions('1234567')));
    }

    public function testAMessageWithoutANumberIsRefused(): void
    {
        $message = static::createStub(MessageInterface::class);
        $message->method('getOptions')->willReturn(new TencentCloudSmsOptions('1234567', 'Shopwell'));
        $message->method('getRecipientId')->willReturn('');

        $this->expectExceptionObject(SmsException::invalidPhoneNumber(''));

        $this->createTransport()->send($message);
    }

    private function createTransport(?SmsClient $client = null, ?string $defaultSignName = null): TencentCloudSmsTransport
    {
        return new TencentCloudSmsTransport($client ?? $this->createMock(SmsClient::class), '1400006666', $defaultSignName);
    }

    private function message(): SmsMessage
    {
        return new SmsMessage(
            '+8613800000000',
            'hello',
            options: new TencentCloudSmsOptions('1234567', 'Shopwell', ['2' => 'second', '1' => 'first'], 'session-1')
        );
    }

    private function clientReturning(SendSmsResponse $response): SmsClient
    {
        $client = static::createStub(SmsClient::class);
        $client->method('__call')->willReturn($response);

        return $client;
    }

    private function clientThrowing(TencentCloudSDKException $error): SmsClient
    {
        $client = static::createStub(SmsClient::class);
        $client->method('__call')->willThrowException($error);

        return $client;
    }

    private function sendStatus(string $code, string $serialNo = 'serial-1', string $message = ''): SendStatus
    {
        $status = new SendStatus();
        $status->SerialNo = $serialNo;
        $status->Code = $code;
        $status->Message = $message;

        return $status;
    }

    private function response(SendStatus ...$statuses): SendSmsResponse
    {
        $response = new SendSmsResponse();
        $response->SendStatusSet = $statuses;
        $response->RequestId = 'req-1';

        return $response;
    }
}
