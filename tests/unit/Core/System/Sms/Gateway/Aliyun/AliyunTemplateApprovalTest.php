<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddSmsTemplateRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddSmsTemplateResponse;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddSmsTemplateResponseBody;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsTemplateRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsTemplateResponse;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsTemplateResponseBody;
use Darabonba\OpenApi\Exceptions\ClientException;
use Darabonba\OpenApi\Exceptions\ServerException;
use Darabonba\OpenApi\Exceptions\ThrottlingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunTemplateApproval;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunTemplateApproval::class)]
class AliyunTemplateApprovalTest extends TestCase
{
    public function testItDeclaresTheThreeFieldsTheApiAsksFor(): void
    {
        static::assertSame([
            [
                'name' => 'templateName',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateName',
                'prefill' => 'templateName',
            ],
            [
                'name' => 'templateContent',
                'type' => 'text',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateContent',
                'prefill' => 'content',
            ],
            [
                'name' => 'templateType',
                'type' => 'select',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateType',
                'prefill' => 'scene',
                'options' => [
                    [
                        'value' => SmsTemplateEntity::SCENE_OTP,
                        'labelSnippet' => 'sms.submission.aliyun.templateType.code',
                    ],
                    [
                        'value' => SmsTemplateEntity::SCENE_TRANSACTION,
                        'labelSnippet' => 'sms.submission.aliyun.templateType.notice',
                    ],
                    [
                        'value' => SmsTemplateEntity::SCENE_MARKETING,
                        'labelSnippet' => 'sms.submission.aliyun.templateType.promotion',
                    ],
                ],
            ],
        ], (new AliyunTemplateApproval())->getTemplateSubmissionFieldDefinitions());
    }

    /**
     * @return iterable<string, array{int|string, string}>
     */
    public static function statusProvider(): iterable
    {
        yield 'in the audit' => [0, TemplateApprovalStatus::PENDING];
        yield 'approved' => [1, TemplateApprovalStatus::APPROVED];
        yield 'rejected' => [2, TemplateApprovalStatus::REJECTED];
        yield 'a status the api does not document' => [3, TemplateApprovalStatus::UNKNOWN];
        yield 'the status as text' => ['1', TemplateApprovalStatus::APPROVED];
        yield 'no number at all' => ['draft', TemplateApprovalStatus::UNKNOWN];
    }

    #[DataProvider('statusProvider')]
    public function testItMapsTheStatusOfTheApiOntoTheVocabularyOfTheModule(int|string $templateStatus, string $expected): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('querySmsTemplate')->willReturn($this->queryResponse([
            'code' => 'OK',
            'message' => 'OK',
            'templateCode' => 'SMS_123456789',
            'templateStatus' => $templateStatus,
        ]));

        $reading = (new AliyunTemplateApproval())->readTemplateApprovalStatus($client, 'SMS_123456789');

        static::assertSame($expected, $reading->status);
        static::assertSame((string) $templateStatus, $reading->providerStatusCode);
    }

    public function testARejectionCarriesTheReasonAndTheNameTheApiKept(): void
    {
        $approval = new AliyunTemplateApproval();
        $reading = $approval->readTemplateApprovalStatus($this->clientQuerying([
            'code' => 'OK',
            'message' => 'OK',
            'templateCode' => 'SMS_123456789',
            'templateStatus' => 2,
            'reason' => 'the body carries no placeholders',
            'templateName' => 'Shopwell login',
        ]), 'SMS_123456789');

        static::assertSame(TemplateApprovalStatus::REJECTED, $reading->status);
        static::assertSame('2', $reading->providerStatusCode);
        static::assertSame('the body carries no placeholders', $reading->providerStatusMessage);
        static::assertSame('Shopwell login', $reading->providerTemplateName);
    }

    public function testATemplateTheAccountDoesNotHoldHasNoState(): void
    {
        $reading = (new AliyunTemplateApproval())->readTemplateApprovalStatus($this->clientQuerying([
            'code' => 'isv.SMS_TEMPLATE_NOT_EXIST',
            'message' => 'the template does not exist',
        ]), 'SMS_123456789');

        static::assertSame(TemplateApprovalStatus::UNKNOWN, $reading->status);
        static::assertSame('isv.SMS_TEMPLATE_NOT_EXIST', $reading->providerStatusCode);
        static::assertSame('the template does not exist', $reading->providerStatusMessage);
    }

    public function testAnAnswerWithoutAStatusIsInvalid(): void
    {
        $this->expectExceptionObject(SmsException::templateAuditResponseInvalid(
            'aliyun',
            'the answer of QuerySmsTemplate carries no TemplateStatus'
        ));

        (new AliyunTemplateApproval())->readTemplateApprovalStatus(
            $this->clientQuerying(['code' => 'OK', 'message' => 'OK']),
            'SMS_123456789'
        );
    }

    public function testATemplateIsReportedWithTheValuesTheCorePrefilled(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('addSmsTemplate')
            ->with(static::callback(static function (AddSmsTemplateRequest $request): bool {
                // an otp template is the type 0 of the api
                return $request->templateName === 'customer.otp.login'
                    && $request->templateContent === 'Your code is {{ code }}'
                    && $request->templateType === 0;
            }))
            ->willReturn($this->addResponse(['code' => 'OK', 'message' => 'OK', 'templateCode' => 'SMS_123456789']));

        $receipt = (new AliyunTemplateApproval())->submitTemplate($client, $this->submission());

        static::assertSame('SMS_123456789', $receipt->templateCode);
        static::assertSame(TemplateApprovalStatus::PENDING, $receipt->status);
        static::assertSame('customer.otp.login', $receipt->providerTemplateName);
        static::assertSame('OK', $receipt->providerCode);
    }

    public function testTheValuesOfTheSubmissionWinOverTheOnesTheCoreKnows(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('addSmsTemplate')
            ->with(static::callback(static fn (AddSmsTemplateRequest $request): bool => $request->templateName === 'Shopwell login'
                && $request->templateContent === 'Your shopwell code is {{ code }}'
                && $request->templateType === 2))
            ->willReturn($this->addResponse(['code' => 'OK', 'message' => 'OK', 'templateCode' => 'SMS_123456789']));

        (new AliyunTemplateApproval())->submitTemplate($client, $this->submission(values: [
            'templateName' => 'Shopwell login',
            'templateContent' => 'Your shopwell code is {{ code }}',
            'templateType' => SmsTemplateEntity::SCENE_MARKETING,
        ]));
    }

    public function testAnImplementationMayHandInTheNumberOfTheApiItself(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('addSmsTemplate')
            ->with(static::callback(static fn (AddSmsTemplateRequest $request): bool => $request->templateType === 2))
            ->willReturn($this->addResponse(['code' => 'OK', 'message' => 'OK', 'templateCode' => 'SMS_123456789']));

        (new AliyunTemplateApproval())->submitTemplate($client, $this->submission(values: ['templateType' => 2]));
    }

    public function testATemplateTheAccountStillHoldsIsNotReportedASecondTime(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->never())->method('addSmsTemplate');
        $client->expects($this->once())
            ->method('querySmsTemplate')
            ->with(static::callback(static fn (QuerySmsTemplateRequest $request): bool => $request->templateCode === 'SMS_123456789'))
            ->willReturn($this->queryResponse([
                'code' => 'OK',
                'message' => 'OK',
                'templateStatus' => 1,
                'templateName' => 'Shopwell login',
            ]));

        $receipt = (new AliyunTemplateApproval())->submitTemplate($client, $this->submission('SMS_123456789'));

        static::assertSame('SMS_123456789', $receipt->templateCode);
        static::assertSame(TemplateApprovalStatus::APPROVED, $receipt->status);
        static::assertSame('Shopwell login', $receipt->providerTemplateName);
        static::assertSame('1', $receipt->providerCode);
    }

    public function testARejectedTemplateIsReplacedByANewOne(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('querySmsTemplate')
            ->willReturn($this->queryResponse(['code' => 'OK', 'message' => 'OK', 'templateStatus' => 2]));
        $client->expects($this->once())
            ->method('addSmsTemplate')
            ->willReturn($this->addResponse(['code' => 'OK', 'message' => 'OK', 'templateCode' => 'SMS_987654321']));

        $receipt = (new AliyunTemplateApproval())->submitTemplate($client, $this->submission('SMS_123456789'));

        static::assertSame('SMS_987654321', $receipt->templateCode);
        static::assertSame(TemplateApprovalStatus::PENDING, $receipt->status);
    }

    public function testATemplateTheAccountDroppedIsReplacedByANewOne(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects($this->once())
            ->method('querySmsTemplate')
            ->willReturn($this->queryResponse([
                'code' => 'isv.SMS_TEMPLATE_NOT_EXIST',
                'message' => 'the template does not exist',
            ]));
        $client->expects($this->once())
            ->method('addSmsTemplate')
            ->willReturn($this->addResponse(['code' => 'OK', 'message' => 'OK', 'templateCode' => 'SMS_987654321']));

        $receipt = (new AliyunTemplateApproval())->submitTemplate($client, $this->submission('SMS_123456789'));

        static::assertSame('SMS_987654321', $receipt->templateCode);
    }

    public function testAnAcceptedSubmissionWithoutATemplateCodeIsInvalid(): void
    {
        $this->expectExceptionObject(SmsException::templateAuditResponseInvalid(
            'aliyun',
            'the answer of AddSmsTemplate carries no TemplateCode'
        ));

        (new AliyunTemplateApproval())->submitTemplate(
            $this->clientAdding(['code' => 'OK', 'message' => 'OK']),
            $this->submission()
        );
    }

    public function testATemplateTheProviderRefusesIsFinal(): void
    {
        $this->expectExceptionObject(SmsException::providerRejected('aliyun', 'isv.SMS_TEMPLATE_ILLEGAL', 'illegal'));

        (new AliyunTemplateApproval())->submitTemplate(
            $this->clientAdding(['code' => 'isv.SMS_TEMPLATE_ILLEGAL', 'message' => 'illegal']),
            $this->submission()
        );
    }

    public function testAFailureAtTheSideOfAliyunIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'isp.SYSTEM_ERROR', 'busy'));

        (new AliyunTemplateApproval())->submitTemplate(
            $this->clientAdding(['code' => 'isp.SYSTEM_ERROR', 'message' => 'busy']),
            $this->submission()
        );
    }

    public function testAReportThatWasThrottledIsWorthAnotherTry(): void
    {
        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'isv.BUSINESS_LIMIT_CONTROL', 'triggered'));

        (new AliyunTemplateApproval())->submitTemplate(
            $this->clientAdding(['code' => 'isv.BUSINESS_LIMIT_CONTROL', 'message' => 'triggered']),
            $this->submission()
        );
    }

    public function testAStatusThatAsksForAnotherTryIsNotACodeOfTheApi(): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('addSmsTemplate')->willThrowException(new ThrottlingException($this->failure('Throttling', 'busy', 429)));

        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'Throttling', 'busy'));

        (new AliyunTemplateApproval())->submitTemplate($client, $this->submission());
    }

    public function testACredentialAliyunRefusedIsFinal(): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('addSmsTemplate')->willThrowException(
            new ClientException($this->failure('InvalidAccessKeyId.NotFound', 'the key is unknown', 400))
        );

        $this->expectExceptionObject(SmsException::providerRejected('aliyun', 'InvalidAccessKeyId.NotFound', 'the key is unknown'));

        (new AliyunTemplateApproval())->submitTemplate($client, $this->submission());
    }

    public function testAReadThatFailsAtTheSideOfAliyunIsWorthAnotherTry(): void
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('querySmsTemplate')->willThrowException(new ServerException($this->failure('InternalError', 'boom', 500)));

        $this->expectExceptionObject(SmsException::providerUnavailable('aliyun', 'InternalError', 'boom'));

        (new AliyunTemplateApproval())->readTemplateApprovalStatus($client, 'SMS_123456789');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function submission(?string $existingTemplateCode = null, array $values = []): TemplateSubmission
    {
        return new TemplateSubmission(
            templateRef: 'customer.otp.login',
            scene: SmsTemplateEntity::SCENE_OTP,
            content: 'Your code is {{ code }}',
            signName: 'Shopwell',
            paramNames: ['code'],
            values: $values,
            existingTemplateCode: $existingTemplateCode,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function clientAdding(array $body): Dysmsapi
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('addSmsTemplate')->willReturn($this->addResponse($body));

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function clientQuerying(array $body): Dysmsapi
    {
        $client = static::createStub(Dysmsapi::class);
        $client->method('querySmsTemplate')->willReturn($this->queryResponse($body));

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function addResponse(array $body): AddSmsTemplateResponse
    {
        return new AddSmsTemplateResponse([
            'headers' => [],
            'statusCode' => 200,
            'body' => new AddSmsTemplateResponseBody($body),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function queryResponse(array $body): QuerySmsTemplateResponse
    {
        return new QuerySmsTemplateResponse([
            'headers' => [],
            'statusCode' => 200,
            'body' => new QuerySmsTemplateResponseBody($body),
        ]);
    }

    /**
     * The exception of the sdk is built from a map that has to carry every key, which is why the
     * failure is assembled here.
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
