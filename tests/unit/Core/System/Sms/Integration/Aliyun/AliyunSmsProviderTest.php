<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Integration\Aliyun;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Integration\Aliyun\AliyunSmsProvider;
use Shopwell\Core\System\Sms\Integration\Aliyun\AliyunSmsTransport;
use Shopwell\Core\System\Sms\Integration\Aliyun\AliyunTemplateApproval;
use Shopwell\Core\System\Sms\Provider\Audit\SmsProviderCapabilities;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunSmsProvider::class)]
class AliyunSmsProviderTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $factory = new AliyunSmsProvider();

        static::assertSame('aliyun', $factory->getTechnicalName());
        static::assertSame('sms.gateway.aliyun', $factory->getLabelSnippet());
        static::assertSame(['CN'], $factory->getSupportedRegions());
        static::assertTrue($factory->supportsTemplateMode());
    }

    public function testItDeclaresTheCredentialsTheAdministrationHasToAsk(): void
    {
        static::assertSame([
            [
                'name' => 'accessKeyId',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.aliyun.accessKeyId',
            ],
            [
                'name' => 'accessKeySecret',
                'type' => 'secret',
                'required' => true,
                'labelSnippet' => 'sms.credential.aliyun.accessKeySecret',
            ],
        ], (new AliyunSmsProvider())->getCredentialDefinitions());
    }

    public function testItBuildsTheTransportOutOfTheStoredCredentials(): void
    {
        $transport = (new AliyunSmsProvider())->createTransport(
            ['accessKeyId' => 'key', 'accessKeySecret' => 'secret'],
            ['signName' => 'Shopwell']
        );

        static::assertInstanceOf(AliyunSmsTransport::class, $transport);
        static::assertSame('aliyun', (string) $transport);
    }

    public function testAGatewayWithoutCredentialsCannotSend(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('aliyun', ['accessKeyId', 'accessKeySecret']));

        (new AliyunSmsProvider())->createTransport([], []);
    }

    public function testTheCoreDiscoversTheAuditCapabilitiesOfAliyun(): void
    {
        // a template of aliyun is delivered only after its audit, and the account can be asked both
        // to report it and for its state - the core learns that from the interfaces the provider
        // implements, so a provider an extension adds is discovered without a change here
        $capabilities = new SmsProviderCapabilities(new SmsProviderRegistry([new AliyunSmsProvider()]));

        static::assertTrue($capabilities->requiresApproval('aliyun'));
        static::assertTrue($capabilities->canSubmit('aliyun'));
        static::assertTrue($capabilities->canReadStatus('aliyun'));
    }

    public function testItAsksTheAuditOfAliyunForTheSubmissionForm(): void
    {
        static::assertSame(
            (new AliyunTemplateApproval())->getTemplateSubmissionFieldDefinitions(),
            (new AliyunSmsProvider())->getTemplateSubmissionFieldDefinitions()
        );
    }

    public function testAReportNeedsTheCredentialsOfTheAccount(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('aliyun', ['accessKeyId', 'accessKeySecret']));

        (new AliyunSmsProvider())->submitTemplate([], [], $this->submission());
    }

    public function testAReadOfTheStateNeedsTheCredentialsOfTheAccount(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('aliyun', ['accessKeyId', 'accessKeySecret']));

        (new AliyunSmsProvider())->readTemplateApprovalStatus([], [], 'SMS_123456789');
    }

    private function submission(): TemplateSubmission
    {
        return new TemplateSubmission(
            templateRef: 'customer.otp.login',
            scene: SmsTemplateEntity::SCENE_OTP,
            content: 'Your code is {{ code }}',
            signName: 'Shopwell',
            paramNames: ['code'],
            values: ['templateName' => 'Shopwell login', 'templateContent' => 'Your code is {{ code }}'],
            existingTemplateCode: null,
        );
    }
}
