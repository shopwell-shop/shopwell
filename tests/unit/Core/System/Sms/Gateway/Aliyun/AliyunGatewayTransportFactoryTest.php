<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Aliyun;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunSmsTransport;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunTemplateApproval;
use Shopwell\Core\System\Sms\Gateway\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\RequiresTemplateApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunGatewayTransportFactory::class)]
class AliyunGatewayTransportFactoryTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $factory = new AliyunGatewayTransportFactory();

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
        ], (new AliyunGatewayTransportFactory())->getCredentialDefinitions());
    }

    public function testItBuildsTheTransportOutOfTheStoredCredentials(): void
    {
        $transport = (new AliyunGatewayTransportFactory())->create(
            ['accessKeyId' => 'key', 'accessKeySecret' => 'secret'],
            ['signName' => 'Shopwell']
        );

        static::assertInstanceOf(AliyunSmsTransport::class, $transport);
        static::assertSame('aliyun', (string) $transport);
    }

    public function testAGatewayWithoutCredentialsCannotSend(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('aliyun', ['accessKeyId', 'accessKeySecret']));

        (new AliyunGatewayTransportFactory())->create([], []);
    }

    public function testItDeclaresTheThreeCapabilitiesOfAnAuditedProvider(): void
    {
        $factory = new AliyunGatewayTransportFactory();

        // a template of aliyun is delivered only after its audit, and the account can be asked both
        // to report it and for its state
        static::assertInstanceOf(RequiresTemplateApprovalInterface::class, $factory);
        static::assertInstanceOf(SubmitsTemplatesForApprovalInterface::class, $factory);
        static::assertInstanceOf(ReadsTemplateApprovalStatusInterface::class, $factory);
    }

    public function testItAsksTheAuditOfAliyunForTheSubmissionForm(): void
    {
        static::assertSame(
            (new AliyunTemplateApproval())->getTemplateSubmissionFieldDefinitions(),
            (new AliyunGatewayTransportFactory())->getTemplateSubmissionFieldDefinitions()
        );
    }

    public function testAReportNeedsTheCredentialsOfTheAccount(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('aliyun', ['accessKeyId', 'accessKeySecret']));

        (new AliyunGatewayTransportFactory())->submitTemplate([], [], $this->submission());
    }

    public function testAReadOfTheStateNeedsTheCredentialsOfTheAccount(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('aliyun', ['accessKeyId', 'accessKeySecret']));

        (new AliyunGatewayTransportFactory())->readTemplateApprovalStatus([], [], 'SMS_123456789');
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
