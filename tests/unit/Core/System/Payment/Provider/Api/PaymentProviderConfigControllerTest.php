<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Api\PaymentProviderConfigController;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderConfigController::class)]
class PaymentProviderConfigControllerTest extends TestCase
{
    public function testTheListPresentsEveryProviderWithItsConfiguration(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class, [$this->storedProviderConfig()]),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class, [$this->storedChannelConfig()]),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $data = $this->decode($controller->list());

        static::assertCount(1, $data['providers']);
        $provider = $data['providers'][0];

        static::assertSame('alipay', $provider['technicalName']);
        static::assertSame('Alipay', $provider['label']);
        static::assertTrue($provider['enabled']);
        static::assertTrue($provider['configured']);
        static::assertSame(['sandbox' => '1'], $provider['settings']);

        static::assertSame('app_id', $provider['credentialFields'][0]['name']);
        static::assertSame('platform-app', $provider['credentialFields'][0]['value']);
        static::assertTrue($provider['credentialFields'][0]['configured']);

        // the private key is secret, so its value never leaves the server, only that one is set
        static::assertSame('private_key', $provider['credentialFields'][1]['name']);
        static::assertNull($provider['credentialFields'][1]['value']);
        static::assertTrue($provider['credentialFields'][1]['configured']);

        static::assertCount(1, $provider['channels']);
        static::assertSame('alipay_h5', $provider['channels'][0]['channel']);
        static::assertSame('redirect', $provider['channels'][0]['flow']);
        static::assertSame(['CNY'], $provider['channels'][0]['currencies']);
        static::assertTrue($provider['channels'][0]['enabled']);
        static::assertTrue($provider['channels'][0]['configured']);
        static::assertSame('wx-appid', $provider['channels'][0]['paramFields'][0]['value']);
    }

    public function testAnUnconfiguredProviderIsPresentedAsNotConfigured(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $data = $this->decode($controller->list());

        $provider = $data['providers'][0];
        static::assertFalse($provider['enabled']);
        static::assertFalse($provider['configured']);
        static::assertNull($provider['credentialFields'][0]['value']);
        static::assertFalse($provider['credentialFields'][0]['configured']);
        static::assertFalse($provider['channels'][0]['enabled']);
        static::assertFalse($provider['channels'][0]['configured']);
    }

    public function testSavingAnUnknownProviderIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), PaymentControllerFixture::repository(PaymentChannelConfigCollection::class), PaymentControllerFixture::providerRegistry());

        $this->expectExceptionObject(PaymentException::unknownProvider('stripe'));

        $controller->save('stripe', $this->payload(['enabled' => true]));
    }

    public function testEnablingAProviderWithoutItsCredentialsIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), PaymentControllerFixture::repository(PaymentChannelConfigCollection::class));

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay', 'app_id'));

        $controller->save('alipay', $this->payload([
            'enabled' => true,
            'credentials' => ['private_key' => 'sk'],
        ]));
    }

    public function testEnablingAProviderWithAllCredentialsStoresIt(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class, [$this->storedProviderConfig()]),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
        );

        $data = $this->decode($controller->save('alipay', $this->payload([
            'enabled' => true,
            'credentials' => ['app_id' => 'platform-app', 'private_key' => 'sk'],
            'settings' => ['sandbox' => '1'],
        ])));

        static::assertTrue($data['provider']['enabled']);
        static::assertTrue($data['provider']['configured']);
    }

    public function testSavingADisabledProviderDoesNotDemandTheCredentials(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
        );

        $data = $this->decode($controller->save('alipay', $this->payload([
            'enabled' => false,
            'settings' => ['sandbox' => '0'],
        ])));

        static::assertSame('alipay', $data['provider']['technicalName']);
        static::assertFalse($data['provider']['enabled']);
    }

    public function testResettingAnUnknownProviderIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), PaymentControllerFixture::repository(PaymentChannelConfigCollection::class), PaymentControllerFixture::providerRegistry());

        $this->expectExceptionObject(PaymentException::unknownProvider('stripe'));

        $controller->reset('stripe');
    }

    public function testResettingAConfiguredProviderDeletesIt(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class, [$this->storedProviderConfig()]),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
        );

        $data = $this->decode($controller->reset('alipay'));

        static::assertSame('alipay', $data['provider']['technicalName']);
        static::assertFalse($data['provider']['enabled']);
        static::assertFalse($data['provider']['configured']);
    }

    public function testResettingAnUnconfiguredProviderIsHarmless(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
        );

        $data = $this->decode($controller->reset('alipay'));

        static::assertSame('alipay', $data['provider']['technicalName']);
    }

    public function testSavingAChannelOfAnUnknownProviderIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), PaymentControllerFixture::repository(PaymentChannelConfigCollection::class), PaymentControllerFixture::providerRegistry());

        $this->expectExceptionObject(PaymentException::unknownProvider('stripe'));

        $controller->saveChannel('stripe', 'alipay_h5', $this->payload([]));
    }

    public function testSavingAnUnknownChannelIsRejected(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $this->expectExceptionObject(PaymentException::unknownChannel('alipay_wap'));

        $controller->saveChannel('alipay', 'alipay_wap', $this->payload([]));
    }

    public function testEnablingAChannelWithoutItsParamsIsRejected(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay_h5', 'appid'));

        $controller->saveChannel('alipay', 'alipay_h5', $this->payload(['enabled' => true]));
    }

    public function testSavingAChannelStoresItsParams(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class, [$this->storedChannelConfig()]),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $data = $this->decode($controller->saveChannel('alipay', 'alipay_h5', $this->payload([
            'enabled' => true,
            'params' => ['appid' => 'wx-appid'],
            'settings' => ['theme' => 'blue'],
        ])));

        static::assertSame('alipay_h5', $data['channel']['channel']);
        static::assertTrue($data['channel']['enabled']);
        static::assertTrue($data['channel']['configured']);
    }

    public function testResettingAnUnknownChannelIsRejected(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $this->expectExceptionObject(PaymentException::unknownChannel('alipay_wap'));

        $controller->resetChannel('alipay', 'alipay_wap');
    }

    public function testResettingAChannelClearsItsConfiguration(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class, [$this->storedChannelConfig()]),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $data = $this->decode($controller->resetChannel('alipay', 'alipay_h5'));

        static::assertSame('alipay_h5', $data['channel']['channel']);
        static::assertFalse($data['channel']['enabled']);
        static::assertFalse($data['channel']['configured']);
    }

    /**
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    private function controller(
        EntityRepository $providerConfigRepository,
        EntityRepository $channelConfigRepository,
        ?PaymentProviderRegistry $providerRegistry = null
    ): PaymentProviderConfigController {
        return new PaymentProviderConfigController(
            $providerRegistry ?? PaymentControllerFixture::providerRegistry($this->provider()),
            new PaymentProviderConfigService($providerConfigRepository, $channelConfigRepository, PaymentControllerFixture::cipher()),
            PaymentControllerFixture::cipher(),
        );
    }

    private function provider(): PaymentProvider
    {
        return new PaymentProvider(
            'alipay',
            'Alipay',
            [
                new PaymentConfigField('app_id', 'app_id', PaymentConfigField::TYPE_TEXT, true),
                new PaymentConfigField('private_key', 'private_key', PaymentConfigField::TYPE_TEXTAREA, true, true),
            ],
            [
                'alipay_h5' => new PaymentChannel(
                    'alipay_h5',
                    'alipay',
                    'Alipay H5',
                    PaymentFlow::REDIRECT,
                    ['CNY'],
                    [PaymentChannel::DEVICE_WAP, PaymentChannel::DEVICE_WEB],
                    [new PaymentConfigField('appid', 'appid', PaymentConfigField::TYPE_TEXT, true)],
                    ['openid' => true],
                ),
            ],
            static::createStub(PaymentGatewayFactory::class),
            static::createStub(PaymentNotificationExtractorInterface::class),
        );
    }

    private function storedProviderConfig(): PaymentProviderConfigEntity
    {
        $config = new PaymentProviderConfigEntity();
        $config->id = Uuid::randomHex();
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = 'alipay';
        $config->enabled = true;
        $config->credentials = PaymentControllerFixture::cipher()->encrypt(['app_id' => 'platform-app', 'private_key' => 'sk']);
        $config->settings = ['sandbox' => '1'];

        return $config;
    }

    private function storedChannelConfig(): PaymentChannelConfigEntity
    {
        $config = new PaymentChannelConfigEntity();
        $config->id = Uuid::randomHex();
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = 'alipay';
        $config->channelTechnicalName = 'alipay_h5';
        $config->enabled = true;
        $config->params = PaymentControllerFixture::cipher()->encrypt(['appid' => 'wx-appid']);

        return $config;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payload(array $payload): Request
    {
        return new Request([], [], [], [], [], ['REQUEST_URI' => '/api/_action/payment/providers/alipay'], (string) json_encode($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
