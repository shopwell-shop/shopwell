<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Controller\SmsGatewayProviderController;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayTransportFactoryInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsGatewayProviderController::class)]
class SmsGatewayProviderControllerTest extends TestCase
{
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
                ],
                [
                    'technicalName' => 'null',
                    'labelSnippet' => 'sms.gateway.null',
                    'supportedRegions' => [],
                    'supportsTemplateMode' => false,
                    'credentialDefinitions' => [],
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

    /**
     * @param list<SmsGatewayTransportFactoryInterface> $factories
     */
    private function createController(array $factories): SmsGatewayProviderController
    {
        return new SmsGatewayProviderController(new SmsGatewayRegistry($factories));
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
}
