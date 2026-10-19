<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\TencentCloud;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudTemplateApproval;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\AddSmsTemplateRequest;
use TencentCloud\Sms\V20210111\Models\AddSmsTemplateResponse;
use TencentCloud\Sms\V20210111\Models\AddTemplateStatus;
use TencentCloud\Sms\V20210111\Models\DescribeSmsTemplateListRequest;
use TencentCloud\Sms\V20210111\Models\DescribeSmsTemplateListResponse;
use TencentCloud\Sms\V20210111\Models\DescribeTemplateListStatus;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * The actions of the sdk are reached through its __call, which is what the mock of the client
 * intercepts - configuring AddSmsTemplate or DescribeSmsTemplateList itself is not possible because
 * the methods do not exist.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(TencentCloudTemplateApproval::class)]
class TencentCloudTemplateApprovalTest extends TestCase
{
    public function testItDeclaresTheFieldsOfASubmission(): void
    {
        static::assertSame([
            [
                'name' => 'templateName',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.submission.tencentcloud.templateName',
                'prefill' => 'templateName',
            ],
            [
                'name' => 'templateContent',
                'type' => 'text',
                'required' => true,
                'labelSnippet' => 'sms.submission.tencentcloud.templateContent',
                'prefill' => 'content',
            ],
            [
                'name' => 'smsType',
                'type' => 'select',
                'required' => true,
                'labelSnippet' => 'sms.submission.tencentcloud.smsType',
                'prefill' => 'scene',
                'options' => [
                    [
                        'value' => SmsTemplateEntity::SCENE_OTP,
                        'labelSnippet' => 'sms.submission.tencentcloud.smsType.verification',
                    ],
                    [
                        'value' => SmsTemplateEntity::SCENE_TRANSACTION,
                        'labelSnippet' => 'sms.submission.tencentcloud.smsType.notification',
                    ],
                    [
                        'value' => SmsTemplateEntity::SCENE_MARKETING,
                        'labelSnippet' => 'sms.submission.tencentcloud.smsType.marketing',
                    ],
                ],
            ],
        ], (new TencentCloudTemplateApproval())->getTemplateSubmissionFieldDefinitions());
    }

    public function testTheRangeOfDeliveryIsNoFieldOfTheForm(): void
    {
        // the international dimension is not implemented in this round, so an operator must not be
        // able to report a template that the transport cannot serve
        foreach ((new TencentCloudTemplateApproval())->getTemplateSubmissionFieldDefinitions() as $definition) {
            static::assertNotSame('international', strtolower($definition['name']));
        }
    }

    public function testItReportsTheTemplateAsADomesticOne(): void
    {
        $client = $this->createMock(SmsClient::class);
        $client->expects($this->once())
            ->method('__call')
            ->with(
                'AddSmsTemplate',
                static::callback(static function (array $arguments): bool {
                    $request = $arguments[0] ?? null;

                    return $request instanceof AddSmsTemplateRequest
                        && $request->TemplateName === 'customer.otp.login'
                        && $request->TemplateContent === 'Your code is {{ code }}.'
                        // the scene of the template is the use it is reported with
                        && $request->SmsType === 3
                        // only domestic templates are reported in this round
                        && $request->International === 0;
                })
            )
            ->willReturn(self::added('1234567'));

        $receipt = $this->approval($client)->submitTemplate($this->submission());

        static::assertSame('1234567', $receipt->templateCode);
        // the provider reviews a reported template afterwards, so a fresh report is pending
        static::assertSame(TemplateApprovalStatus::PENDING, $receipt->status);
        static::assertSame('customer.otp.login', $receipt->providerTemplateName);
        static::assertNull($receipt->providerCode);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, int}>
     */
    public static function sceneTypeProvider(): iterable
    {
        yield 'the scene of the template decides' => ['marketing', [], 1];
        yield 'the scene the operator confirmed decides' => ['otp', ['smsType' => 'transaction'], 2];
        yield 'a scene the module does not know is a notification' => ['unknown-scene', [], 2];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('sceneTypeProvider')]
    public function testTheTypeOfTheTemplateFollowsTheScene(string $scene, array $values, int $expectedType): void
    {
        $client = $this->createMock(SmsClient::class);
        $client->expects($this->once())
            ->method('__call')
            ->with(
                'AddSmsTemplate',
                static::callback(static function (array $arguments) use ($expectedType): bool {
                    $request = $arguments[0] ?? null;

                    return $request instanceof AddSmsTemplateRequest && $request->SmsType === $expectedType;
                })
            )
            ->willReturn(self::added('1234567'));

        $receipt = $this->approval($client)->submitTemplate($this->submission(null, $values, $scene));

        static::assertSame('1234567', $receipt->templateCode);
    }

    public function testItFallsBackToTheReferenceOfTheTemplate(): void
    {
        $client = $this->createMock(SmsClient::class);
        $client->expects($this->once())
            ->method('__call')
            ->with(
                'AddSmsTemplate',
                static::callback(static function (array $arguments): bool {
                    $request = $arguments[0] ?? null;

                    return $request instanceof AddSmsTemplateRequest
                        && $request->TemplateName === 'customer.otp.login';
                })
            )
            ->willReturn(self::added('1234567'));

        $receipt = $this->approval($client)->submitTemplate($this->submission(null, ['templateName' => '']));

        static::assertSame('1234567', $receipt->templateCode);
    }

    public function testAnAnswerWithoutATemplateIdIsInvalid(): void
    {
        $this->expectExceptionObject(SmsException::templateAuditResponseInvalid(
            'tencentcloud',
            'The response of tencent cloud carried no template id.'
        ));

        $response = new AddSmsTemplateResponse();
        $response->RequestId = 'req-1';

        $this->approval($this->clientReturning($response))->submitTemplate($this->submission());
    }

    public function testItReusesTheTemplateTheProviderStillHolds(): void
    {
        $client = $this->createMock(SmsClient::class);
        // a second AddSmsTemplate would double the count and fail the expectation
        $client->expects($this->once())
            ->method('__call')
            ->with(
                'DescribeSmsTemplateList',
                static::callback(static function (array $arguments): bool {
                    $request = $arguments[0] ?? null;

                    return $request instanceof DescribeSmsTemplateListRequest
                        && $request->TemplateIdSet === [1111111]
                        && $request->International === 0;
                })
            )
            ->willReturn(self::listing(self::listed(1111111, 0, 'approved')));

        $receipt = $this->approval($client)->submitTemplate($this->submission('1111111'));

        static::assertSame('1111111', $receipt->templateCode);
        static::assertSame(TemplateApprovalStatus::APPROVED, $receipt->status);
        static::assertSame('0', $receipt->providerCode);
        static::assertSame('approved', $receipt->providerMessage);
        static::assertSame('Shopwell', $receipt->providerTemplateName);
    }

    public function testItReportsAgainWhenTheProviderRefusedTheTemplate(): void
    {
        $actions = [];

        $client = $this->createStub(SmsClient::class);
        $client->method('__call')->willReturnCallback(function (string $action) use (&$actions): object {
            $actions[] = $action;

            if ($action === 'AddSmsTemplate') {
                return self::added('2222222');
            }

            return self::listing(self::listed(1111111, -1, 'the body is not allowed'));
        });

        $receipt = $this->approval($client)->submitTemplate($this->submission('1111111'));

        // a refused template cannot be sent, so the shop reports the body again
        static::assertSame(['DescribeSmsTemplateList', 'AddSmsTemplate'], $actions);
        static::assertSame('2222222', $receipt->templateCode);
        static::assertSame(TemplateApprovalStatus::PENDING, $receipt->status);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function statusCodeProvider(): iterable
    {
        yield 'approved and effective' => [0, TemplateApprovalStatus::APPROVED];
        yield 'under review' => [1, TemplateApprovalStatus::PENDING];
        yield 'approved but not effective yet' => [2, TemplateApprovalStatus::PENDING];
        yield 'refused' => [-1, TemplateApprovalStatus::REJECTED];
        yield 'not documented by the provider' => [7, TemplateApprovalStatus::UNKNOWN];
    }

    #[DataProvider('statusCodeProvider')]
    public function testItMapsTheStatusCodeOfTheProvider(int $statusCode, string $expectedStatus): void
    {
        $reading = $this->approval($this->clientReturning(self::listing(self::listed(1234567, $statusCode))))
            ->readTemplateApprovalStatus('1234567');

        static::assertSame($expectedStatus, $reading->status);
        static::assertSame((string) $statusCode, $reading->providerStatusCode);
    }

    public function testAnApprovedButNotEffectiveTemplateMayNotBeSent(): void
    {
        $reading = $this->approval($this->clientReturning(self::listing(self::listed(1234567, 2))))
            ->readTemplateApprovalStatus('1234567');

        // the api does not deliver with such a template, so it is no approval for the shop either
        static::assertSame(TemplateApprovalStatus::PENDING, $reading->status);
        static::assertFalse(TemplateApprovalStatus::isSendable($reading->status));
    }

    public function testATemplateTheProviderDoesNotListIsUnknown(): void
    {
        $reading = $this->approval($this->clientReturning(self::listing()))
            ->readTemplateApprovalStatus('1234567');

        static::assertSame(TemplateApprovalStatus::UNKNOWN, $reading->status);
        static::assertFalse(TemplateApprovalStatus::isSendable($reading->status));
        static::assertSame('', $reading->providerStatusCode);
        static::assertStringContainsString('1234567', $reading->providerStatusMessage);
    }

    public function testAListedTemplateWithoutAStatusCodeIsInvalid(): void
    {
        $listed = new DescribeTemplateListStatus();
        $listed->TemplateId = 1234567;
        $listed->TemplateName = 'Shopwell';

        $this->expectExceptionObject(SmsException::templateAuditResponseInvalid(
            'tencentcloud',
            'The provider listed the template without a status code.'
        ));

        $this->approval($this->clientReturning(self::listing($listed)))
            ->readTemplateApprovalStatus('1234567');
    }

    public function testARejectionOfTheApiIsFinal(): void
    {
        $this->expectExceptionObject(SmsException::providerRejected(
            'tencentcloud',
            'FailedOperation.TemplateIncorrectOrUnapproved',
            'the template is not approved'
        ));

        $this->approval($this->clientThrowing(new TencentCloudSDKException(
            'FailedOperation.TemplateIncorrectOrUnapproved',
            'the template is not approved',
            'req-1'
        )))->submitTemplate($this->submission());
    }

    public function testASystemErrorAtTheProviderIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable(
            'tencentcloud',
            'InternalError.SystemError',
            'boom'
        ));

        $this->approval($this->clientThrowing(new TencentCloudSDKException('InternalError.SystemError', 'boom', 'req-1')))
            ->readTemplateApprovalStatus('1234567');
    }

    public function testARequestThatNeverReachedTheApiIsWorthAnotherTry(): void
    {
        // the sdk wraps a failure of the transport itself without a code
        $this->expectExceptionObject(SmsException::providerUnavailable(
            'tencentcloud',
            'TransportError',
            'Connection timed out'
        ));

        $this->approval($this->clientThrowing(new TencentCloudSDKException('', 'Connection timed out')))
            ->submitTemplate($this->submission());
    }

    private function approval(?SmsClient $client = null): TencentCloudTemplateApproval
    {
        return new TencentCloudTemplateApproval([
            'secretId' => 'id',
            'secretKey' => 'key',
            'sdkAppId' => '1400006666',
            'region' => 'ap-guangzhou',
        ], $client ?? $this->createMock(SmsClient::class));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function submission(
        ?string $existingTemplateCode = null,
        array $values = ['templateName' => 'customer.otp.login', 'smsType' => 'otp'],
        string $scene = 'otp',
    ): TemplateSubmission {
        return new TemplateSubmission(
            'customer.otp.login',
            $scene,
            'Your code is {{ code }}.',
            'Shopwell',
            ['code'],
            $values,
            $existingTemplateCode,
        );
    }

    private function clientReturning(object $response): SmsClient
    {
        $client = $this->createStub(SmsClient::class);
        $client->method('__call')->willReturn($response);

        return $client;
    }

    private function clientThrowing(TencentCloudSDKException $error): SmsClient
    {
        $client = $this->createStub(SmsClient::class);
        $client->method('__call')->willThrowException($error);

        return $client;
    }

    private static function added(string $templateId): AddSmsTemplateResponse
    {
        $status = new AddTemplateStatus();
        $status->TemplateId = $templateId;

        $response = new AddSmsTemplateResponse();
        $response->AddTemplateStatus = $status;
        $response->RequestId = 'req-1';

        return $response;
    }

    private static function listing(DescribeTemplateListStatus ...$statuses): DescribeSmsTemplateListResponse
    {
        $response = new DescribeSmsTemplateListResponse();
        $response->DescribeTemplateStatusSet = $statuses;
        $response->RequestId = 'req-1';

        return $response;
    }

    private static function listed(int $templateId, int $statusCode, string $reviewReply = ''): DescribeTemplateListStatus
    {
        $status = new DescribeTemplateListStatus();
        $status->TemplateId = $templateId;
        $status->StatusCode = $statusCode;
        $status->ReviewReply = $reviewReply;
        $status->TemplateName = 'Shopwell';
        $status->International = 0;

        return $status;
    }
}
