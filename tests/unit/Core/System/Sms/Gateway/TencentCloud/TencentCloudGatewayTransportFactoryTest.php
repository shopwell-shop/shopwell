<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\TencentCloud;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudSmsTransport;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudTemplateApproval;
use Shopwell\Core\System\Sms\SmsException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TencentCloudGatewayTransportFactory::class)]
class TencentCloudGatewayTransportFactoryTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $factory = new TencentCloudGatewayTransportFactory();

        static::assertSame('tencentcloud', $factory->getTechnicalName());
        static::assertSame('sms.gateway.tencentcloud', $factory->getLabelSnippet());
        // the international dimension of a template is not implemented, so the gateway serves the
        // domestic numbers only instead of claiming a range its transport cannot deliver to
        static::assertSame(['CN'], $factory->getSupportedRegions());
        static::assertTrue($factory->supportsTemplateMode());
    }

    public function testTheCoreDiscoversTheAuditCapabilitiesOfTencentCloud(): void
    {
        // the core learns the capabilities from the interfaces the provider implements, so a
        // provider an extension adds is discovered without a change here
        $capabilities = new SmsGatewayAuditCapabilities(new SmsGatewayRegistry([new TencentCloudGatewayTransportFactory()]));

        static::assertTrue($capabilities->requiresApproval('tencentcloud'));
        static::assertTrue($capabilities->canSubmit('tencentcloud'));
        static::assertTrue($capabilities->canReadStatus('tencentcloud'));
    }

    public function testItDescribesTheSubmissionFormOfTheProvider(): void
    {
        $factory = new TencentCloudGatewayTransportFactory();

        static::assertSame(
            (new TencentCloudTemplateApproval())->getTemplateSubmissionFieldDefinitions(),
            $factory->getTemplateSubmissionFieldDefinitions()
        );
    }

    public function testItDeclaresTheCredentialsTheAdministrationHasToAsk(): void
    {
        static::assertSame([
            [
                'name' => 'secretId',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.secretId',
            ],
            [
                'name' => 'secretKey',
                'type' => 'secret',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.secretKey',
            ],
            [
                'name' => 'sdkAppId',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.sdkAppId',
            ],
            [
                'name' => 'region',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.region',
            ],
        ], (new TencentCloudGatewayTransportFactory())->getCredentialDefinitions());
    }

    public function testItBuildsTheTransportOutOfTheStoredCredentials(): void
    {
        $transport = (new TencentCloudGatewayTransportFactory())->create(
            ['secretId' => 'id', 'secretKey' => 'key', 'sdkAppId' => '1400006666', 'region' => 'ap-guangzhou'],
            []
        );

        static::assertInstanceOf(TencentCloudSmsTransport::class, $transport);
        static::assertSame('tencentcloud', (string) $transport);
    }

    public function testAGatewayWithoutCredentialsCannotSend(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('tencentcloud', ['secretId', 'secretKey', 'sdkAppId', 'region']));

        (new TencentCloudGatewayTransportFactory())->create([], []);
    }

    public function testOnlyTheCredentialsThatAreMissingAreReported(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('tencentcloud', ['sdkAppId']));

        (new TencentCloudGatewayTransportFactory())->create([
            'secretId' => 'id',
            'secretKey' => 'key',
            'region' => 'ap-guangzhou',
        ], []);
    }

    public function testAGatewayWithoutCredentialsCannotReportATemplate(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('tencentcloud', ['secretId', 'secretKey', 'sdkAppId', 'region']));

        (new TencentCloudGatewayTransportFactory())->submitTemplate([], [], new TemplateSubmission(
            'customer.otp.login',
            'otp',
            'Your code is {{ code }}.',
            'Shopwell',
            ['code'],
            ['templateName' => 'customer.otp.login', 'smsType' => 3],
            null,
        ));
    }

    public function testAGatewayWithoutCredentialsCannotReadTheStateOfATemplate(): void
    {
        $this->expectExceptionObject(SmsException::credentialsIncomplete('tencentcloud', ['secretId', 'secretKey', 'sdkAppId', 'region']));

        (new TencentCloudGatewayTransportFactory())->readTemplateApprovalStatus([], [], '1234567');
    }
}
