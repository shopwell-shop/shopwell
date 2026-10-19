<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Template\Audit;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
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

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateAuditService::class)]
class SmsTemplateAuditServiceTest extends TestCase
{
    private const MAPPING_ID = '0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b';
    private const TEMPLATE_ID = '1a8f7a5b1c2d4e3f8a9b0c1d2e3f4a5c';
    private const GATEWAY_ID = '2b8f7a5b1c2d4e3f8a9b0c1d2e3f4a5d';
    private const TECHNICAL_NAME = 'aliyun';
    private const TEMPLATE_REF = 'customer.otp.login';
    private const NOW = '2026-10-14T10:00:00+00:00';

    /**
     * The submission form a provider of the audit capabilities declares.
     */
    private const FIELD_DEFINITIONS = [
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
            'default' => 0,
            'options' => [
                ['value' => 0, 'labelSnippet' => 'sms.submission.aliyun.templateType.verification'],
                ['value' => 1, 'labelSnippet' => 'sms.submission.aliyun.templateType.notification'],
            ],
        ],
    ];

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testItReportsARejectedTemplateAgainAndWritesTheAnswerOntoTheRow(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_OLD', auditStatus: TemplateApprovalStatus::REJECTED);
        $mappingRepository = $this->createMappingRepository($mapping, $mapping);

        $submitted = null;
        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('submitTemplate')
            ->with(
                ['accessKeyId' => 'key'],
                ['timeout' => 5],
                static::isInstanceOf(TemplateSubmission::class),
            )
            ->willReturnCallback(static function (array $credentials, array $options, TemplateSubmission $submission) use (&$submitted): TemplateSubmissionReceipt {
                $submitted = $submission;

                return new TemplateSubmissionReceipt(
                    templateCode: 'SMS_123456789',
                    status: TemplateApprovalStatus::PENDING,
                    providerTemplateName: 'customer_otp_login',
                    providerCode: '0',
                    providerMessage: 'OK',
                );
            });

        $service = $this->createService($factory, $mappingRepository);

        $result = $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());

        static::assertSame($mapping, $result);
        static::assertInstanceOf(TemplateSubmission::class, $submitted);
        static::assertSame(self::TEMPLATE_REF, $submitted->templateRef);
        static::assertSame(SmsTemplateEntity::SCENE_OTP, $submitted->scene);
        static::assertSame('Your code is {{ code }}.', $submitted->content);
        static::assertSame('Shopwell', $submitted->signName);
        static::assertSame(['code'], $submitted->paramNames);
        static::assertSame($this->createFieldValues(), $submitted->values);
        // the row knows a template of an earlier attempt, the provider reuses it
        static::assertSame('SMS_OLD', $submitted->existingTemplateCode);

        static::assertCount(1, $mappingRepository->updates);

        $payload = $mappingRepository->updates[0][0];

        // no column of the table is extended, the answer of the provider lands in the custom fields
        static::assertSame(['id', 'templateCode', 'reportedContent', 'reportedAt', 'auditStatus', 'customFields'], array_keys($payload));
        static::assertSame(self::MAPPING_ID, $payload['id']);
        static::assertSame('SMS_123456789', $payload['templateCode']);
        static::assertSame('Your code is {{ code }}.', $payload['reportedContent']);
        static::assertSame(TemplateApprovalStatus::PENDING, $payload['auditStatus']);
        static::assertInstanceOf(\DateTimeInterface::class, $payload['reportedAt']);
        static::assertSame([
            'sms_provider_status_code' => '0',
            'sms_provider_status_message' => 'OK',
            'sms_provider_template_name' => 'customer_otp_login',
        ], $payload['customFields']);
    }

    public function testAPendingMappingIsReturnedWithoutCallingTheProviderAgain(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::PENDING);
        $mappingRepository = $this->createMappingRepository($mapping);

        $factory = $this->createFactory();
        $factory->expects($this->never())->method('submitTemplate');

        $service = $this->createService($factory, $mappingRepository);

        $result = $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());

        static::assertSame($mapping, $result);
        static::assertSame([], $mappingRepository->updates);
    }

    public function testAnApprovedMappingIsReturnedWithoutCallingTheProviderAgain(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::APPROVED);
        $mappingRepository = $this->createMappingRepository($mapping);

        $factory = $this->createFactory();
        $factory->expects($this->never())->method('submitTemplate');

        $service = $this->createService($factory, $mappingRepository);

        static::assertSame($mapping, $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues()));
        static::assertSame([], $mappingRepository->updates);
    }

    public function testASuspendedMappingIsReportedAgain(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::SUSPENDED);
        $mappingRepository = $this->createMappingRepository($mapping, $mapping);

        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('submitTemplate')
            ->willReturn(new TemplateSubmissionReceipt('SMS_123456789', TemplateApprovalStatus::PENDING));

        $service = $this->createService($factory, $mappingRepository);

        $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());

        static::assertCount(1, $mappingRepository->updates);
    }

    public function testItRefusesASubmissionOfAProviderThatCannotSubmitTemplates(): void
    {
        $mapping = $this->createMapping(technicalName: 'null');
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService(new NullGatewayTransportFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateSubmissionUnsupported('null'));

        $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());
    }

    public function testItRefusesASubmissionOfARowThatHoldsForEveryAccount(): void
    {
        $mapping = $this->createMapping(gatewayId: null);
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService($this->createFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateAuditAccountRequired(self::TEMPLATE_ID, self::TECHNICAL_NAME));

        $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());
    }

    public function testItRefusesASubmissionOverAnAccountThatIsDisabled(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $gateway = $this->createGateway();
        $gateway->enabled = false;

        $service = $this->createService(
            $this->createFactory(),
            $mappingRepository,
            gatewayRepository: $this->createGatewayRepository($gateway),
        );

        $this->expectExceptionObject(SmsException::templateNotConfigured(self::TEMPLATE_ID, self::TECHNICAL_NAME));

        $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());
    }

    public function testItRefusesASubmissionOfATemplateWithoutABody(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService(
            $this->createFactory(),
            $mappingRepository,
            templateRepository: $this->createTemplateRepository($this->createTemplate(null)),
        );

        $this->expectExceptionObject(SmsException::templateContentMissing(self::TEMPLATE_REF));

        $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues());
    }

    public function testItRefusesASubmissionThatMissesAFieldTheProviderDeclares(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService($this->createFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateSubmissionInvalid(
            self::TEMPLATE_REF,
            self::TECHNICAL_NAME,
            ['"templateContent" is required', '"templateType" is required'],
        ));

        $service->submit($this->context, self::MAPPING_ID, ['templateName' => 'customer_otp_login']);
    }

    public function testItRefusesASubmissionWithAValueTheProviderDoesNotOffer(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService($this->createFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateSubmissionInvalid(
            self::TEMPLATE_REF,
            self::TECHNICAL_NAME,
            ['"templateType" is not one of the offered values'],
        ));

        $service->submit($this->context, self::MAPPING_ID, $this->createFieldValues(['templateType' => 7]));
    }

    public function testItKeepsTheStateAndTheRawWordsOfTheProviderOnTheRow(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::PENDING);
        $mappingRepository = $this->createMappingRepository($mapping, $mapping);

        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('readTemplateApprovalStatus')
            ->with(['accessKeyId' => 'key'], ['timeout' => 5], 'SMS_123456789')
            ->willReturn(new TemplateApprovalReading(
                status: TemplateApprovalStatus::APPROVED,
                providerStatusCode: '0',
                providerStatusMessage: '审核通过',
                providerTemplateName: 'customer_otp_login',
            ));

        $service = $this->createService($factory, $mappingRepository);

        $result = $service->refresh($this->context, self::MAPPING_ID);

        static::assertSame($mapping, $result);
        static::assertCount(1, $mappingRepository->updates);

        $payload = $mappingRepository->updates[0][0];

        static::assertSame(['id', 'auditStatus', 'customFields'], array_keys($payload));
        static::assertSame(TemplateApprovalStatus::APPROVED, $payload['auditStatus']);
        static::assertSame([
            'sms_provider_status_code' => '0',
            'sms_provider_status_message' => '审核通过',
            'sms_last_synced_at' => '2026-10-14 10:00:00.000',
            'sms_provider_template_name' => 'customer_otp_login',
        ], $payload['customFields']);
    }

    public function testAFailingStatusReadWritesNothing(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', auditStatus: TemplateApprovalStatus::PENDING);
        $mappingRepository = $this->createMappingRepository($mapping);

        $factory = $this->createFactory();
        $factory->method('readTemplateApprovalStatus')
            ->willThrowException(SmsException::providerUnavailable(self::TECHNICAL_NAME, '500', 'the provider is down'));

        $service = $this->createService($factory, $mappingRepository);

        try {
            $service->refresh($this->context, self::MAPPING_ID);
            static::fail('the failure of the provider must reach the caller');
        } catch (SmsException $exception) {
            static::assertSame(SmsException::PROVIDER_UNAVAILABLE, $exception->getErrorCode());
        }

        // the row must not look as if it had been reconciled
        static::assertSame([], $mappingRepository->updates);
    }

    public function testItRefusesAStatusReadOfARowThatWasNeverReported(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService($this->createFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateNotConfigured(self::TEMPLATE_ID, self::TECHNICAL_NAME));

        $service->refresh($this->context, self::MAPPING_ID);
    }

    public function testItRefusesAStatusReadOfAProviderThatCannotReportOne(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', technicalName: 'null');
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService(new NullGatewayTransportFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateStatusReadUnsupported('null'));

        $service->refresh($this->context, self::MAPPING_ID);
    }

    public function testItConfirmsTheStateByHandAndWritesTheAuditTrail(): void
    {
        $mapping = $this->createMapping(templateCode: 'SMS_123456789', technicalName: 'null');
        $mappingRepository = $this->createMappingRepository($mapping, $mapping);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('log_entry', static::callback(function (array $row): bool {
                static::assertSame(16, \strlen($row['id']));
                static::assertSame('sms.template_mapping.confirmed_manually', $row['message']);
                static::assertSame(Level::Info->value, $row['level']);
                static::assertSame('sms', $row['channel']);
                static::assertNull($row['extra']);
                static::assertSame('2026-10-14 10:00:00.000', $row['created_at']);
                static::assertSame([
                    'mappingId' => self::MAPPING_ID,
                    'templateRef' => self::TEMPLATE_REF,
                    'technicalName' => 'null',
                    'gatewayId' => self::GATEWAY_ID,
                    'fromStatus' => TemplateApprovalStatus::PENDING,
                    'toStatus' => TemplateApprovalStatus::APPROVED,
                    'note' => 'approved at the console',
                    'operatorId' => null,
                    'occurredAt' => '2026-10-14 10:00:00.000',
                ], json_decode((string) $row['context'], true, 512, \JSON_THROW_ON_ERROR));

                return true;
            }));

        $service = $this->createService(new NullGatewayTransportFactory(), $mappingRepository, connection: $connection);

        $result = $service->confirmManually($this->context, self::MAPPING_ID, TemplateApprovalStatus::APPROVED, 'approved at the console');

        static::assertSame($mapping, $result);
        static::assertSame([[
            'id' => self::MAPPING_ID,
            'auditStatus' => TemplateApprovalStatus::APPROVED,
        ]], $mappingRepository->updates[0]);
    }

    public function testItNamesTheOperatorOfAManualConfirmation(): void
    {
        $mapping = $this->createMapping(technicalName: 'null');
        $mappingRepository = $this->createMappingRepository($mapping, $mapping);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('log_entry', static::callback(static function (array $row): bool {
                $trail = json_decode((string) $row['context'], true, 512, \JSON_THROW_ON_ERROR);

                return $trail['operatorId'] === 'operator-id';
            }));

        $service = $this->createService(new NullGatewayTransportFactory(), $mappingRepository, connection: $connection);

        $service->confirmManually(
            Context::createDefaultContext(new AdminApiSource('operator-id')),
            self::MAPPING_ID,
            TemplateApprovalStatus::SUSPENDED,
            'the provider suspended the signature',
        );
    }

    public function testItRefusesAManualConfirmationOfAProviderThatCanReportTheState(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $service = $this->createService($this->createFactory(), $mappingRepository, connection: $connection);

        $this->expectExceptionObject(SmsException::templateStatusReadUnsupported(self::TECHNICAL_NAME));

        $service->confirmManually($this->context, self::MAPPING_ID, TemplateApprovalStatus::APPROVED, 'approved at the console');
    }

    public function testItRefusesAManualConfirmationOfAStateTheShopDerivesItself(): void
    {
        $mapping = $this->createMapping(technicalName: 'null');
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService(new NullGatewayTransportFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateAuditConfirmationInvalid(
            self::TEMPLATE_REF,
            'null',
            ['"pending" cannot be confirmed by hand'],
        ));

        $service->confirmManually($this->context, self::MAPPING_ID, TemplateApprovalStatus::PENDING, 'looks good');
    }

    public function testItRefusesAManualConfirmationWithoutANote(): void
    {
        $mapping = $this->createMapping(technicalName: 'null');
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService(new NullGatewayTransportFactory(), $mappingRepository);

        $this->expectExceptionObject(SmsException::templateAuditConfirmationInvalid(
            self::TEMPLATE_REF,
            'null',
            ['a note is required'],
        ));

        $service->confirmManually($this->context, self::MAPPING_ID, TemplateApprovalStatus::APPROVED, '   ');
    }

    public function testEnsureMappingReturnsTheRowThatAlreadyExists(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository($mapping);

        $service = $this->createService($this->createFactory(), $mappingRepository);

        static::assertSame(
            $mapping,
            $service->ensureMapping($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, self::GATEWAY_ID)
        );
        static::assertSame([], $mappingRepository->creates);
    }

    public function testEnsureMappingCreatesTheRowThatDoesNotExistYet(): void
    {
        $mapping = $this->createMapping();
        $mappingRepository = $this->createMappingRepository(null, $mapping);

        $service = $this->createService($this->createFactory(), $mappingRepository);

        static::assertSame(
            $mapping,
            $service->ensureMapping($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, self::GATEWAY_ID)
        );

        static::assertCount(1, $mappingRepository->creates);

        $created = $mappingRepository->creates[0][0];

        static::assertSame(self::TEMPLATE_ID, $created['templateId']);
        static::assertSame(self::TECHNICAL_NAME, $created['technicalName']);
        static::assertSame(self::GATEWAY_ID, $created['gatewayId']);
        static::assertIsString($created['id']);
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
     * @param StaticEntityRepository<SmsTemplateMappingCollection> $mappingRepository
     * @param StaticEntityRepository<SmsGatewayCollection>|null $gatewayRepository
     * @param StaticEntityRepository<SmsTemplateCollection>|null $templateRepository
     */
    private function createService(
        SmsGatewayTransportFactoryInterface $factory,
        StaticEntityRepository $mappingRepository,
        ?StaticEntityRepository $gatewayRepository = null,
        ?StaticEntityRepository $templateRepository = null,
        ?Connection $connection = null,
    ): SmsTemplateAuditService {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new SmsTemplateAuditService(
            new SmsGatewayAuditCapabilities(new SmsGatewayRegistry([$factory])),
            $mappingRepository,
            $gatewayRepository ?? $this->createGatewayRepository(),
            $templateRepository ?? $this->createTemplateRepository(),
            new SmsTemplateMappingLocator($mappingRepository),
            new SmsTemplateBodyReader(),
            $connection ?? $this->createMock(Connection::class),
            $clock,
        );
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

    /**
     * @return StaticEntityRepository<SmsGatewayCollection>
     */
    private function createGatewayRepository(?SmsGatewayEntity $gateway = null): StaticEntityRepository
    {
        return StaticEntityRepository::of(SmsGatewayCollection::class, [
            new SmsGatewayCollection($gateway === null ? [$this->createGateway()] : [$gateway]),
        ]);
    }

    /**
     * @return StaticEntityRepository<SmsTemplateCollection>
     */
    private function createTemplateRepository(?SmsTemplateEntity $template = null): StaticEntityRepository
    {
        return StaticEntityRepository::of(SmsTemplateCollection::class, [
            new SmsTemplateCollection($template === null ? [$this->createTemplate()] : [$template]),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createFieldValues(array $overrides = []): array
    {
        return array_merge([
            'templateName' => 'customer_otp_login',
            'templateContent' => 'Your code is {{ code }}.',
            'templateType' => 0,
        ], $overrides);
    }

    private function createMapping(
        ?string $templateCode = null,
        string $auditStatus = TemplateApprovalStatus::PENDING,
        string $technicalName = self::TECHNICAL_NAME,
        ?string $gatewayId = self::GATEWAY_ID,
    ): SmsTemplateMappingEntity {
        $mapping = new SmsTemplateMappingEntity();
        $mapping->id = self::MAPPING_ID;
        $mapping->setUniqueIdentifier($mapping->id);
        $mapping->templateId = self::TEMPLATE_ID;
        $mapping->technicalName = $technicalName;
        $mapping->gatewayId = $gatewayId;
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
        $gateway->options = ['timeout' => 5];

        return $gateway;
    }

    private function createTemplate(?string $content = 'Your code is {{ code }}.'): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = self::TEMPLATE_ID;
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = self::TEMPLATE_REF;
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->content = $content;
        $template->enabled = true;

        return $template;
    }
}
