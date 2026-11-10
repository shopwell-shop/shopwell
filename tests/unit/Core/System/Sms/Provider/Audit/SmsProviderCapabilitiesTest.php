<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Integration\Log\LogSmsProvider;
use Shopwell\Core\System\Sms\Integration\Null\NullSmsProvider;
use Shopwell\Core\System\Sms\Integration\Null\NullSmsTransport;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Provider\Audit\RequiresTemplateApprovalInterface;
use Shopwell\Core\System\Sms\Provider\Audit\SmsProviderCapabilities;
use Shopwell\Core\System\Sms\Provider\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateSubmissionReceipt;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Util\CountryDialCodeResolver;
use Shopwell\Core\System\Sms\Util\PhoneNumberMasker;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\System\Sms\Util\ShippedCountryDialCodeProvider;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsProviderCapabilities::class)]
class SmsProviderCapabilitiesTest extends TestCase
{
    private const SUBMISSION_FIELDS = [[
        'name' => 'templateContent',
        'type' => 'text',
        'required' => true,
        'labelSnippet' => 'sms.submission.acme.templateContent',
        'prefill' => 'content',
    ]];

    public function testTheGatewaysOfThePlatformDeclareNoAuditCapability(): void
    {
        $capabilities = $this->createCapabilities();

        static::assertSame([
            [
                'technicalName' => 'null',
                'labelSnippet' => 'sms.gateway.null',
                'requiresApproval' => false,
                'canSubmit' => false,
                'canReadStatus' => false,
                'submissionFields' => [],
            ],
            [
                'technicalName' => 'log',
                'labelSnippet' => 'sms.gateway.log',
                'requiresApproval' => false,
                'canSubmit' => false,
                'canReadStatus' => false,
                'submissionFields' => [],
            ],
        ], $capabilities->describeAll());

        static::assertFalse($capabilities->requiresApproval('null'));
        static::assertFalse($capabilities->canSubmit('null'));
        static::assertFalse($capabilities->canReadStatus('null'));
        static::assertFalse($capabilities->requiresApproval('log'));
        static::assertFalse($capabilities->canSubmit('log'));
        static::assertFalse($capabilities->canReadStatus('log'));
    }

    public function testTheCapabilitiesOfAProviderAreDescribedByTheInterfacesItImplements(): void
    {
        $capabilities = new SmsProviderCapabilities(new SmsProviderRegistry([$this->createAuditedFactory()]));

        static::assertSame([
            [
                'technicalName' => 'acme',
                'labelSnippet' => 'sms.gateway.acme',
                'requiresApproval' => true,
                'canSubmit' => true,
                'canReadStatus' => true,
                'submissionFields' => self::SUBMISSION_FIELDS,
            ],
        ], $capabilities->describeAll());
    }

    public function testAnUnknownTechnicalNameDeclaresNoCapability(): void
    {
        $capabilities = $this->createCapabilities();

        static::assertFalse($capabilities->requiresApproval('twilio'));
        static::assertFalse($capabilities->canSubmit('twilio'));
        static::assertFalse($capabilities->canReadStatus('twilio'));
    }

    public function testTheFullCapableProviderIsFoundByTheInterfaceItImplements(): void
    {
        $factory = $this->createAuditedFactory();
        $capabilities = new SmsProviderCapabilities(new SmsProviderRegistry([$factory]));

        static::assertTrue($capabilities->requiresApproval('acme'));
        static::assertTrue($capabilities->canSubmit('acme'));
        static::assertTrue($capabilities->canReadStatus('acme'));
        static::assertSame($factory, $capabilities->submitter('acme'));
        static::assertSame($factory, $capabilities->reader('acme'));
    }

    public function testASubmissionOfAProviderWithoutTheSubmissionCapabilityIsRefused(): void
    {
        $capabilities = $this->createCapabilities();

        $this->expectExceptionObject(SmsException::templateSubmissionUnsupported('log'));

        $capabilities->submitter('log');
    }

    public function testAStatusReadOfAProviderWithoutTheReadCapabilityIsRefused(): void
    {
        $capabilities = $this->createCapabilities();

        $this->expectExceptionObject(SmsException::templateStatusReadUnsupported('log'));

        $capabilities->reader('log');
    }

    public function testTheApprovalStatusOptionsCarryTheFiveStatesWithTheirSnippetKeys(): void
    {
        $capabilities = $this->createCapabilities();

        static::assertSame([
            ['value' => TemplateApprovalStatus::PENDING, 'labelSnippet' => 'sms.approval.status.pending'],
            ['value' => TemplateApprovalStatus::APPROVED, 'labelSnippet' => 'sms.approval.status.approved'],
            ['value' => TemplateApprovalStatus::REJECTED, 'labelSnippet' => 'sms.approval.status.rejected'],
            ['value' => TemplateApprovalStatus::SUSPENDED, 'labelSnippet' => 'sms.approval.status.suspended'],
            ['value' => TemplateApprovalStatus::UNKNOWN, 'labelSnippet' => 'sms.approval.status.unknown'],
        ], $capabilities->approvalStatusOptions());
    }

    private function createCapabilities(): SmsProviderCapabilities
    {
        return new SmsProviderCapabilities(new SmsProviderRegistry([
            new NullSmsProvider(),
            new LogSmsProvider(
                new NullLogger(),
                new PhoneNumberNormalizer(new CountryDialCodeResolver([new ShippedCountryDialCodeProvider()])),
                new PhoneNumberMasker(),
            ),
        ]));
    }

    private function createAuditedFactory(): AbstractSmsProvider&RequiresTemplateApprovalInterface&SubmitsTemplatesForApprovalInterface&ReadsTemplateApprovalStatusInterface
    {
        return new class extends AbstractSmsProvider implements RequiresTemplateApprovalInterface, SubmitsTemplatesForApprovalInterface, ReadsTemplateApprovalStatusInterface {
            public function getTechnicalName(): string
            {
                return 'acme';
            }

            public function getLabelSnippet(): string
            {
                return 'sms.gateway.acme';
            }

            /**
             * @return list<string>
             */
            public function getSupportedRegions(): array
            {
                return ['GLOBAL'];
            }

            public function supportsTemplateMode(): bool
            {
                return true;
            }

            /**
             * @return list<array{name: string, type: 'string'|'secret'|'json'|'bool', required: bool, labelSnippet: string}>
             */
            public function getCredentialDefinitions(): array
            {
                return [];
            }

            public function createTransport(
                array $credentials,
                array $options,
                ?EventDispatcherInterface $dispatcher = null,
            ): TransportInterface {
                return new NullSmsTransport($dispatcher);
            }

            /**
             * @return list<array{
             *     name: string,
             *     type: 'string'|'text'|'int'|'select',
             *     required: bool,
             *     labelSnippet: string,
             *     prefill?: 'templateName'|'content'|'scene',
             * }>
             */
            public function getTemplateSubmissionFieldDefinitions(): array
            {
                return [[
                    'name' => 'templateContent',
                    'type' => 'text',
                    'required' => true,
                    'labelSnippet' => 'sms.submission.acme.templateContent',
                    'prefill' => 'content',
                ]];
            }

            public function submitTemplate(
                array $credentials,
                array $options,
                TemplateSubmission $submission,
            ): TemplateSubmissionReceipt {
                return new TemplateSubmissionReceipt(
                    templateCode: 'unused',
                    status: TemplateApprovalStatus::PENDING,
                );
            }

            public function readTemplateApprovalStatus(
                array $credentials,
                array $options,
                string $templateCode,
            ): TemplateApprovalReading {
                return new TemplateApprovalReading(
                    status: TemplateApprovalStatus::UNKNOWN,
                    providerStatusCode: '',
                    providerStatusMessage: '',
                );
            }
        };
    }
}
