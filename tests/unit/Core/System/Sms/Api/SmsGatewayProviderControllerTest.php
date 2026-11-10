<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Api\SmsGatewayProviderController;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Provider\Audit\RequiresTemplateApprovalInterface;
use Shopwell\Core\System\Sms\Provider\Audit\SmsProviderCapabilities;
use Shopwell\Core\System\Sms\Provider\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Tests\Unit\Core\System\Sms\Provider\Fixtures\AuditedSmsProvider;
use Shopwell\Tests\Unit\Core\System\Sms\Provider\Fixtures\SubmittingSmsProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsGatewayProviderController::class)]
class SmsGatewayProviderControllerTest extends TestCase
{
    private const NO_AUDIT = [
        'requiresApproval' => false,
        'canSubmit' => false,
        'canReadStatus' => false,
        'submissionFields' => [],
    ];

    public function testListAnswersEveryProviderOfTheRegistryWithTheShapeOfItsCredentials(): void
    {
        $controller = $this->createController([
            $this->factory(
                technicalName: 'aliyun',
                labelSnippet: 'sms.gateway.aliyun',
                supportedRegions: ['CN'],
                supportsTemplateMode: true,
                credentialDefinitions: [
                    ['name' => 'accessKeyId', 'type' => 'string', 'required' => true, 'labelSnippet' => 'sms.gateway.aliyun.accessKeyId'],
                    ['name' => 'accessKeySecret', 'type' => 'secret', 'required' => true, 'labelSnippet' => 'sms.gateway.aliyun.accessKeySecret'],
                ]
            ),
            $this->factory(
                technicalName: 'null',
                labelSnippet: 'sms.gateway.null',
                supportedRegions: [],
                supportsTemplateMode: false,
                credentialDefinitions: []
            ),
        ]);

        $content = $controller->list(Context::createDefaultContext())->getContent();
        static::assertIsString($content);

        static::assertSame(
            [
                [
                    'technicalName' => 'aliyun',
                    'labelSnippet' => 'sms.gateway.aliyun',
                    'supportedRegions' => ['CN'],
                    'supportsTemplateMode' => true,
                    'credentialDefinitions' => [
                        ['name' => 'accessKeyId', 'type' => 'string', 'required' => true, 'labelSnippet' => 'sms.gateway.aliyun.accessKeyId'],
                        ['name' => 'accessKeySecret', 'type' => 'secret', 'required' => true, 'labelSnippet' => 'sms.gateway.aliyun.accessKeySecret'],
                    ],
                    'optionDefinitions' => [],
                    'audit' => self::NO_AUDIT,
                ],
                [
                    'technicalName' => 'null',
                    'labelSnippet' => 'sms.gateway.null',
                    'supportedRegions' => [],
                    'supportsTemplateMode' => false,
                    'credentialDefinitions' => [],
                    'optionDefinitions' => [],
                    'audit' => self::NO_AUDIT,
                ],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['providers']
        );
    }

    public function testListReturnsNoProviderWhenNoneIsRegistered(): void
    {
        $content = $this->createController([])->list(Context::createDefaultContext())->getContent();
        static::assertIsString($content);

        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['providers']);
    }

    public function testListNeverAnswersAValueOfACredential(): void
    {
        $controller = $this->createController([
            $this->factory(
                technicalName: 'aliyun',
                labelSnippet: 'sms.gateway.aliyun',
                supportedRegions: ['CN'],
                supportsTemplateMode: true,
                credentialDefinitions: [
                    ['name' => 'accessKeySecret', 'type' => 'secret', 'required' => true, 'labelSnippet' => 'sms.gateway.aliyun.accessKeySecret'],
                ]
            ),
        ]);

        $content = $controller->list(Context::createDefaultContext())->getContent();
        static::assertIsString($content);

        $provider = json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['providers'][0];

        static::assertSame(['name', 'type', 'required', 'labelSnippet'], array_keys($provider['credentialDefinitions'][0]));
        static::assertArrayNotHasKey('credentials', $provider);
        static::assertArrayNotHasKey('value', $provider['credentialDefinitions'][0]);
    }

    public function testListSaysWhichProviderTakesPartInTheAuditAndWhichFormItDeclares(): void
    {
        $controller = $this->createController([
            $this->auditedFactory(),
        ]);

        $content = $controller->list(Context::createDefaultContext())->getContent();
        static::assertIsString($content);

        $provider = json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['providers'][0];

        static::assertSame([
            'requiresApproval' => true,
            'canSubmit' => true,
            'canReadStatus' => true,
            'submissionFields' => [
                [
                    'name' => 'templateName',
                    'type' => 'string',
                    'required' => true,
                    'labelSnippet' => 'sms.submission.aliyun.templateName',
                    'prefill' => 'templateName',
                ],
            ],
        ], $provider['audit']);
    }

    public function testTheAuditBlockSaysWhichHalfOfTheLoopAProviderTakesPartIn(): void
    {
        $content = $this->createController([$this->submittingOnlyFactory()])->list(Context::createDefaultContext())->getContent();
        static::assertIsString($content);

        $provider = json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['providers'][0];

        // a provider that needs an approved template but can neither report one nor read its state,
        // which is what the administration shows as "report it at the console"
        static::assertFalse($provider['audit']['requiresApproval']);
        static::assertTrue($provider['audit']['canSubmit']);
        static::assertFalse($provider['audit']['canReadStatus']);
        static::assertSame([], $provider['audit']['submissionFields']);
    }

    public function testListAnswersTheStatesAnOperatorMayConfirmWithTheirLabels(): void
    {
        $content = $this->createController([])->list(Context::createDefaultContext())->getContent();
        static::assertIsString($content);

        // the administration keeps no list of the states, it renders these
        static::assertSame([
            ['value' => 'pending', 'labelSnippet' => 'sms.approval.status.pending'],
            ['value' => 'approved', 'labelSnippet' => 'sms.approval.status.approved'],
            ['value' => 'rejected', 'labelSnippet' => 'sms.approval.status.rejected'],
            ['value' => 'suspended', 'labelSnippet' => 'sms.approval.status.suspended'],
            ['value' => 'unknown', 'labelSnippet' => 'sms.approval.status.unknown'],
        ], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['approvalStatuses']);
    }

    public function testTemplateViewersCanReadProviderMetadataWithoutAccountCredentials(): void
    {
        $source = new AdminApiSource('user');
        $source->setPermissions(['sms_template:read']);
        $response = $this->createController([])->list(new Context($source));
        static::assertSame(200, $response->getStatusCode());
    }

    public function testUnrelatedRolesCannotReadProviderMetadata(): void
    {
        $this->expectExceptionObject(SmsException::missingProviderReadPrivilege());
        $this->createController([])->list(new Context(new AdminApiSource('user')));
    }

    /**
     * @param list<AbstractSmsProvider> $factories
     */
    private function createController(array $factories): SmsGatewayProviderController
    {
        $registry = new SmsProviderRegistry($factories);

        return new SmsGatewayProviderController($registry, new SmsProviderCapabilities($registry));
    }

    /**
     * @param list<string> $supportedRegions
     * @param list<array{name: string, type: string, required: bool, labelSnippet: string}> $credentialDefinitions
     */
    private function factory(
        string $technicalName,
        string $labelSnippet,
        array $supportedRegions,
        bool $supportsTemplateMode,
        array $credentialDefinitions,
    ): AbstractSmsProvider {
        $factory = static::createStub(AbstractSmsProvider::class);
        $factory->method('getTechnicalName')->willReturn($technicalName);
        $factory->method('getLabelSnippet')->willReturn($labelSnippet);
        $factory->method('getSupportedRegions')->willReturn($supportedRegions);
        $factory->method('supportsTemplateMode')->willReturn($supportsTemplateMode);
        $factory->method('getCredentialDefinitions')->willReturn($credentialDefinitions);

        return $factory;
    }

    /**
     * A provider that declares the whole audit loop.
     */
    private function auditedFactory(): AbstractSmsProvider&RequiresTemplateApprovalInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&Stub
    {
        /** @var AbstractSmsProvider&RequiresTemplateApprovalInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&Stub $factory */
        $factory = static::createStub(AuditedSmsProvider::class);

        $factory->method('getTechnicalName')->willReturn('aliyun');
        $factory->method('getLabelSnippet')->willReturn('sms.gateway.aliyun');
        $factory->method('getTemplateSubmissionFieldDefinitions')->willReturn([
            [
                'name' => 'templateName',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateName',
                'prefill' => 'templateName',
            ],
        ]);

        return $factory;
    }

    /**
     * A provider that reports its templates but cannot be asked for their state.
     */
    private function submittingOnlyFactory(): AbstractSmsProvider&SubmitsTemplatesForApprovalInterface&Stub
    {
        /** @var AbstractSmsProvider&SubmitsTemplatesForApprovalInterface&Stub $factory */
        $factory = static::createStub(SubmittingSmsProvider::class);

        $factory->method('getTechnicalName')->willReturn('tencentcloud');
        $factory->method('getLabelSnippet')->willReturn('sms.gateway.tencentcloud');
        $factory->method('getTemplateSubmissionFieldDefinitions')->willReturn([]);

        return $factory;
    }
}
