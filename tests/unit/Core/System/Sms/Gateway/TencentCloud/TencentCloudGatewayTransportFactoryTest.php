<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\TencentCloud;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudSmsTransport;
use Shopwell\Core\System\Sms\SmsUnrecoverableException;

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
        static::assertSame(['CN', 'GLOBAL'], $factory->getSupportedRegions());
        static::assertTrue($factory->supportsTemplateMode());
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
        $this->expectExceptionObject(SmsUnrecoverableException::credentialsIncomplete('tencentcloud', ['secretId', 'secretKey', 'sdkAppId', 'region']));

        (new TencentCloudGatewayTransportFactory())->create([], []);
    }

    public function testOnlyTheCredentialsThatAreMissingAreReported(): void
    {
        $this->expectExceptionObject(SmsUnrecoverableException::credentialsIncomplete('tencentcloud', ['sdkAppId']));

        (new TencentCloudGatewayTransportFactory())->create([
            'secretId' => 'id',
            'secretKey' => 'key',
            'region' => 'ap-guangzhou',
        ], []);
    }
}
