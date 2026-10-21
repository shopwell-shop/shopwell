<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Sms\Api\SmsTemplateAuditController;
use Shopwell\Core\System\Sms\Gateway\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmissionReceipt;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateAuditService;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateBodyReader;
use Shopwell\Core\System\Sms\Template\SmsTemplateCollection;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateMappingLocator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateAuditController::class)]
class SmsTemplateAuditControllerTest extends TestCase
{
    private const MAPPING_ID = '0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b';
    private const TEMPLATE_ID = '1a8f7a5b1c2d4e3f8a9b0c1d2e3f4a5c';
    private const GATEWAY_ID = '2b8f7a5b1c2d4e3f8a9b0c1d2e3f4a5d';
    private const TECHNICAL_NAME = 'aliyun';
    private const TEMPLATE_REF = 'customer.otp.login';
    private const NOW = '2026-10-14T10:00:00+00:00';
    private const ROUTE_SUBMIT = 'api.action.sms-template-audit.submit';

    /**
     * The submission form the provider of these cases declares.
     */
    private const FIELD_DEFINITIONS = [
        [
            'name' => 'templateName',
            'type' => 'string',
            'required' => true,
            'labelSnippet' => 'sms.submission.aliyun.templateName',
            'prefill' => 'templateName',
        ],
    ];

    public function testSubmitAnswersTheStateAndTheCodeOfTheReportedTemplate(): void
    {
        $reported = $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::PENDING);

        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('submitTemplate')
            ->willReturn(new TemplateSubmissionReceipt(
                templateCode: 'SMS_123456789',
                status: TemplateApprovalStatus::PENDING,
                providerTemplateName: 'customer_otp_login',
                providerCode: '0',
                providerMessage: 'OK',
            ));

        $controller = $this->createController(
            $factory,
            $this->createMappingRepository($this->createMapping(), $reported)
        );

        $response = $controller->submit($this->request([
            'fieldValues' => ['templateName' => 'customer_otp_login'],
        ]), self::MAPPING_ID);

        static::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'status' => TemplateApprovalStatus::PENDING,
            'templateCode' => 'SMS_123456789',
            'errorCode' => null,
            'errorMessage' => null,
        ], $this->decode($response));
    }

    public function testSubmitAnswersTheCodeOfAProviderThatCannotSubmitTemplates(): void
    {
        $controller = $this->createController(
            new NullGatewayTransportFactory(),
            $this->createMappingRepository($this->createMapping(technicalName: 'null'))
        );

        $response = $controller->submit($this->request(), self::MAPPING_ID);

        // the screen offers the button anyway, because a provider may be added by an extension after
        // the row was written - the answer has to say why nothing happened
        static::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([
            'status' => null,
            'templateCode' => null,
            'errorCode' => SmsException::TEMPLATE_SUBMISSION_UNSUPPORTED,
            'errorMessage' => SmsException::templateSubmissionUnsupported('null')->getMessage(),
        ], $this->decode($response));
    }

    public function testRefreshAnswersTheStateTheProviderReports(): void
    {
        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('readTemplateApprovalStatus')
            ->with(['accessKeyId' => 'key'], [], 'SMS_123456789')
            ->willReturn(new TemplateApprovalReading(
                status: TemplateApprovalStatus::APPROVED,
                providerStatusCode: '0',
                providerStatusMessage: 'OK',
            ));

        $controller = $this->createController(
            $factory,
            $this->createMappingRepository(
                $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::PENDING),
                $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::APPROVED),
            )
        );

        $response = $controller->refresh($this->request(), self::MAPPING_ID);

        static::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'status' => TemplateApprovalStatus::APPROVED,
            'templateCode' => 'SMS_123456789',
            'errorCode' => null,
            'errorMessage' => null,
        ], $this->decode($response));
    }

    public function testAStateTheProviderDoesNotKnowIsASuccessfulAnswerAndNotAFailure(): void
    {
        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('readTemplateApprovalStatus')
            ->willReturn(new TemplateApprovalReading(
                status: TemplateApprovalStatus::UNKNOWN,
                providerStatusCode: 'isv.SMS_TEMPLATE_NOT_EXIST',
                providerStatusMessage: 'The template does not exist.',
            ));

        $controller = $this->createController(
            $factory,
            $this->createMappingRepository(
                $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::APPROVED),
                $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::UNKNOWN),
            )
        );

        $response = $controller->refresh($this->request(), self::MAPPING_ID);

        // the provider answered, so the step succeeded - a template deleted at the console is the
        // everyday case. The null of a failure means the shop did not talk to the provider at all,
        // and the two must not be told apart by the null of one and the word of the other.
        static::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'status' => TemplateApprovalStatus::UNKNOWN,
            'templateCode' => 'SMS_123456789',
            'errorCode' => null,
            'errorMessage' => null,
        ], $this->decode($response));
    }

    public function testConfirmAnswersTheStateTheOperatorRecorded(): void
    {
        $controller = $this->createController(
            new NullGatewayTransportFactory(),
            $this->createMappingRepository(
                $this->createMapping(technicalName: 'null', templateCode: 'SMS_123456789'),
                $this->createMapping(technicalName: 'null', templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::APPROVED),
            )
        );

        $response = $controller->confirm($this->request([
            'status' => TemplateApprovalStatus::APPROVED,
            'note' => 'approved at the console',
        ]), self::MAPPING_ID);

        static::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'status' => TemplateApprovalStatus::APPROVED,
            'templateCode' => 'SMS_123456789',
            'errorCode' => null,
            'errorMessage' => null,
        ], $this->decode($response));
    }

    public function testConfirmOfAProviderThatReportsItsStateItselfIsAnsweredWithTheCodeOfTheReason(): void
    {
        $controller = $this->createController(
            $this->createFactoryStub(),
            $this->createMappingRepository($this->createMapping())
        );

        $response = $controller->confirm($this->request([
            'status' => TemplateApprovalStatus::APPROVED,
            'note' => 'approved at the console',
        ]), self::MAPPING_ID);

        // the answer says which half of the loop this provider takes part in, so the screen offers
        // the refresh of that row instead of a field the operator must not fill in
        static::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([
            'status' => null,
            'templateCode' => null,
            'errorCode' => SmsException::TEMPLATE_STATUS_READ_UNSUPPORTED,
            'errorMessage' => SmsException::templateStatusReadUnsupported(self::TECHNICAL_NAME)->getMessage(),
        ], $this->decode($response));
    }

    public function testAConfirmationWithoutAStatusIsAnsweredAsTheViolationItIs(): void
    {
        $controller = $this->createController(
            new NullGatewayTransportFactory(),
            $this->createMappingRepository($this->createMapping(technicalName: 'null'))
        );

        $response = $controller->confirm($this->request([]), self::MAPPING_ID);

        static::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());

        $payload = $this->decode($response);

        static::assertSame(SmsException::TEMPLATE_AUDIT_CONFIRMATION_INVALID, $payload['errorCode']);
        static::assertSame([
            'status' => null,
            'templateCode' => null,
        ], ['status' => $payload['status'], 'templateCode' => $payload['templateCode']]);
    }

    public function testAnActionWithoutTheContextOfTheMiddlewareIsRefused(): void
    {
        $controller = $this->createController(
            $this->createFactoryStub(),
            $this->createMappingRepository($this->createMapping())
        );

        $request = $this->request();
        $request->attributes->remove(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        $response = $controller->submit($request, self::MAPPING_ID);

        // a default context would read the row under a context the caller never asked for, and the
        // provider must not be called at all
        static::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([
            'status' => null,
            'templateCode' => null,
            'errorCode' => SmsException::MISSING_CONTEXT,
            'errorMessage' => SmsException::missingContext(self::ROUTE_SUBMIT)->getMessage(),
        ], $this->decode($response));
    }

    /**
     * A provider that reports templates and can be asked for their state.
     */
    private function createFactory(): SmsGatewayTransportFactoryInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&MockObject
    {
        /** @var SmsGatewayTransportFactoryInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&MockObject $factory */
        $factory = $this->createMockForIntersectionOfInterfaces([
            SmsGatewayTransportFactoryInterface::class,
            SubmitsTemplatesForApprovalInterface::class,
            ReadsTemplateApprovalStatusInterface::class,
        ]);

        $factory->method('getTechnicalName')->willReturn(self::TECHNICAL_NAME);
        $factory->method('getLabelSnippet')->willReturn('sms.gateway.aliyun');
        $factory->method('getTemplateSubmissionFieldDefinitions')->willReturn(self::FIELD_DEFINITIONS);

        return $factory;
    }

    /**
     * The same provider for the tests that never look at what it answered.
     */
    private function createFactoryStub(): SmsGatewayTransportFactoryInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&Stub
    {
        /** @var SmsGatewayTransportFactoryInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&Stub $factory */
        $factory = static::createStubForIntersectionOfInterfaces([
            SmsGatewayTransportFactoryInterface::class,
            SubmitsTemplatesForApprovalInterface::class,
            ReadsTemplateApprovalStatusInterface::class,
        ]);

        $factory->method('getTechnicalName')->willReturn(self::TECHNICAL_NAME);
        $factory->method('getLabelSnippet')->willReturn('sms.gateway.aliyun');
        $factory->method('getTemplateSubmissionFieldDefinitions')->willReturn(self::FIELD_DEFINITIONS);

        return $factory;
    }

    /**
     * @param StaticEntityRepository<SmsTemplateMappingCollection> $mappingRepository
     */
    private function createController(
        SmsGatewayTransportFactoryInterface $factory,
        StaticEntityRepository $mappingRepository,
    ): SmsTemplateAuditController {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        $service = new SmsTemplateAuditService(
            new SmsGatewayAuditCapabilities(new SmsGatewayRegistry([$factory])),
            $mappingRepository,
            StaticEntityRepository::of(SmsGatewayCollection::class, [new SmsGatewayCollection([$this->createGateway()])]),
            StaticEntityRepository::of(SmsTemplateCollection::class, [new SmsTemplateCollection([$this->createTemplate()])]),
            new SmsTemplateMappingLocator($mappingRepository),
            new SmsTemplateBodyReader(),
            static::createStub(Connection::class),
            $clock,
            new EventDispatcher(),
        );

        return new SmsTemplateAuditController($service);
    }

    /**
     * A request as the api middleware hands it to the action: the context of the caller is an
     * attribute, the payload of a json body is the request of the parameter bag.
     *
     * @param array<string, mixed> $payload
     */
    private function request(array $payload = []): Request
    {
        $request = new Request([], $payload);
        $request->attributes->set('_route', self::ROUTE_SUBMIT);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext());

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return StaticEntityRepository<SmsTemplateMappingCollection>
     */
    private function createMappingRepository(?SmsTemplateMappingEntity ...$answers): StaticEntityRepository
    {
        $searches = [];

        foreach ($answers as $answer) {
            $searches[] = new SmsTemplateMappingCollection($answer === null ? [] : [$answer]);
        }

        return StaticEntityRepository::of(SmsTemplateMappingCollection::class, $searches);
    }

    private function createMapping(
        string $technicalName = self::TECHNICAL_NAME,
        ?string $templateCode = null,
        string $auditStatus = TemplateApprovalStatus::PENDING,
    ): SmsTemplateMappingEntity {
        $mapping = new SmsTemplateMappingEntity();
        $mapping->id = self::MAPPING_ID;
        $mapping->setUniqueIdentifier($mapping->id);
        $mapping->templateId = self::TEMPLATE_ID;
        $mapping->technicalName = $technicalName;
        $mapping->gatewayId = self::GATEWAY_ID;
        $mapping->templateCode = $templateCode;
        $mapping->signName = 'Shopwell';
        $mapping->auditStatus = $auditStatus;

        return $mapping;
    }

    private function createGateway(): SmsGatewayEntity
    {
        $gateway = new SmsGatewayEntity();
        $gateway->id = self::GATEWAY_ID;
        $gateway->setUniqueIdentifier($gateway->id);
        $gateway->technicalName = self::TECHNICAL_NAME;
        $gateway->enabled = true;
        $gateway->credentials = ['accessKeyId' => 'key'];
        $gateway->options = [];

        return $gateway;
    }

    private function createTemplate(): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = self::TEMPLATE_ID;
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = self::TEMPLATE_REF;
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->content = 'Your code is {{ code }}.';
        $template->enabled = true;

        return $template;
    }
}
