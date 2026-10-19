<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Api\SmsGatewayProviderController;
use Shopwell\Core\System\Sms\Gateway\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\RequiresTemplateApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;

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

        $content = $controller->list()->getContent();
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
                    'audit' => self::NO_AUDIT,
                ],
                [
                    'technicalName' => 'null',
                    'labelSnippet' => 'sms.gateway.null',
                    'supportedRegions' => [],
                    'supportsTemplateMode' => false,
                    'credentialDefinitions' => [],
                    'audit' => self::NO_AUDIT,
                ],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['providers']
        );
    }

    public function testListReturnsNoProviderWhenNoneIsRegistered(): void
    {
        $content = $this->createController([])->list()->getContent();
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

        $content = $controller->list()->getContent();
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

        $content = $controller->list()->getContent();
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
        $content = $this->createController([$this->submittingOnlyFactory()])->list()->getContent();
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
        $content = $this->createController([])->list()->getContent();
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

    /**
     * @param list<SmsGatewayTransportFactoryInterface> $factories
     */
    private function createController(array $factories): SmsGatewayProviderController
    {
        $registry = new SmsGatewayRegistry($factories);

        return new SmsGatewayProviderController($registry, new SmsGatewayAuditCapabilities($registry));
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
    ): SmsGatewayTransportFactoryInterface {
        $factory = static::createStub(SmsGatewayTransportFactoryInterface::class);
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
    private function auditedFactory(): SmsGatewayTransportFactoryInterface&RequiresTemplateApprovalInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&MockObject
    {
        /** @var SmsGatewayTransportFactoryInterface&RequiresTemplateApprovalInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface&MockObject $factory */
        $factory = $this->createMockForIntersectionOfInterfaces([
            SmsGatewayTransportFactoryInterface::class,
            RequiresTemplateApprovalInterface::class,
            SubmitsTemplatesForApprovalInterface::class,
            ReadsTemplateApprovalStatusInterface::class,
        ]);

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
    private function submittingOnlyFactory(): SmsGatewayTransportFactoryInterface&SubmitsTemplatesForApprovalInterface&MockObject
    {
        /** @var SmsGatewayTransportFactoryInterface&SubmitsTemplatesForApprovalInterface&MockObject $factory */
        $factory = $this->createMockForIntersectionOfInterfaces([
            SmsGatewayTransportFactoryInterface::class,
            SubmitsTemplatesForApprovalInterface::class,
        ]);

        $factory->method('getTechnicalName')->willReturn('tencentcloud');
        $factory->method('getLabelSnippet')->willReturn('sms.gateway.tencentcloud');
        $factory->method('getTemplateSubmissionFieldDefinitions')->willReturn([]);

        return $factory;
    }
}
