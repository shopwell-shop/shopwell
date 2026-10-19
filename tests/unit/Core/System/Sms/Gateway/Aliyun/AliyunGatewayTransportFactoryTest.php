<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Aliyun;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunSmsTransport;
use Shopwell\Core\System\Sms\SmsException;

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
}
