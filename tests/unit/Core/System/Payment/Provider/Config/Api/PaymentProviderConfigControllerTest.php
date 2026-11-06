<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Config\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\Config\Api\PaymentProviderConfigController;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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

        // a secret only reports that it is configured; leaving its input empty keeps the stored value
        static::assertSame('private_key', $provider['credentialFields'][1]['name']);
        static::assertTrue($provider['credentialFields'][1]['secret']);
        static::assertNull($provider['credentialFields'][1]['value']);
        static::assertTrue($provider['credentialFields'][1]['configured']);
        static::assertStringNotContainsString('sk', (string) json_encode($provider));

        static::assertCount(1, $provider['products']);
        static::assertSame('alipay_h5', $provider['products'][0]['product']);
        static::assertSame(['redirect'], $provider['products'][0]['interactions']);
        static::assertSame(['CNY'], $provider['products'][0]['currencies']);
        static::assertTrue($provider['products'][0]['enabled']);
        static::assertTrue($provider['products'][0]['configured']);
        static::assertSame('wx-appid', $provider['products'][0]['paramFields'][0]['value']);
        static::assertNull($provider['products'][0]['paramFields'][1]['value']);
        static::assertTrue($provider['products'][0]['paramFields'][1]['configured']);
    }

    public function testDefinitionsExposeSchemasWithoutReadingMerchantConfiguration(): void
    {
        $providers = static::createMock(EntityRepository::class);
        $providers->expects($this->never())->method('search');
        $products = static::createMock(EntityRepository::class);
        $products->expects($this->never())->method('search');
        $controller = $this->controller($providers, $products, PaymentControllerFixture::providerRegistry($this->provider()));

        $data = $this->decode($controller->definitions());

        static::assertSame('alipay', $data['providers'][0]['technicalName']);
        static::assertSame('alipay_h5', $data['providers'][0]['products'][0]['product']);
        static::assertSame('alipay', $data['providers'][0]['products'][0]['method']);
        static::assertNull($data['providers'][0]['accountId']);
        static::assertNull($data['providers'][0]['credentialFields'][0]['value']);
        static::assertNull($data['providers'][0]['products'][0]['paramFields'][0]['value']);
        static::assertFalse($data['providers'][0]['credentialFields'][0]['configured']);
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
        static::assertFalse($provider['products'][0]['enabled']);
        static::assertFalse($provider['products'][0]['configured']);
    }

    public function testSavingAnUnknownProviderIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), PaymentControllerFixture::repository(PaymentChannelConfigCollection::class), PaymentControllerFixture::providerRegistry());

        $this->expectExceptionObject(PaymentProviderException::unknownProvider('stripe'));

        $controller->save('stripe', $this->payload(['enabled' => true]));
    }

    public function testEnablingAProviderWithoutItsCredentialsIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), PaymentControllerFixture::repository(PaymentChannelConfigCollection::class));

        $this->expectExceptionObject(PaymentGatewayException::missingCredentials('alipay', 'app_id'));

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

        $this->expectExceptionObject(PaymentProviderException::unknownProvider('stripe'));

        $controller->reset('stripe');
    }

    public function testResettingAConfiguredProviderDisablesAdmissionAndRetainsConfiguredKeys(): void
    {
        $configured = $this->storedProviderConfig();
        $disabled = clone $configured;
        $disabled->enabled = false;
        $repository = StaticEntityRepository::of(PaymentProviderConfigCollection::class, []);
        $repository->searches = [new PaymentProviderConfigCollection([$configured]), new PaymentProviderConfigCollection([$disabled])];
        $controller = $this->controller($repository, PaymentControllerFixture::repository(PaymentChannelConfigCollection::class));

        $data = $this->decode($controller->reset('alipay'));

        static::assertSame('alipay', $data['provider']['technicalName']);
        static::assertFalse($data['provider']['enabled']);
        static::assertTrue($data['provider']['configured']);
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

        $this->expectExceptionObject(PaymentProviderException::unknownProvider('stripe'));

        $controller->saveChannel('stripe', 'alipay_h5', $this->payload([]));
    }

    public function testSavingAnUnknownChannelIsRejected(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $this->expectExceptionObject(PaymentProviderException::unknownChannel('alipay_wap'));

        $controller->saveChannel('alipay', 'alipay_wap', $this->payload([]));
    }

    public function testEnablingAChannelWithoutAConfiguredProviderIsRejected(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $this->expectExceptionObject(PaymentProviderException::providerNotConfigured('alipay'));

        $controller->saveChannel('alipay', 'alipay_h5', $this->payload(['enabled' => true]));
    }

    public function testSavingAChannelStoresItsParams(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class, [$this->storedProviderConfig()]),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class, [$this->storedChannelConfig()]),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $data = $this->decode($controller->saveChannel('alipay', 'alipay_h5', $this->payload([
            'enabled' => true,
            'params' => ['appid' => 'wx-appid'],
            'settings' => ['theme' => 'blue'],
        ])));

        static::assertSame('alipay_h5', $data['channel']['product']);
        static::assertTrue($data['channel']['enabled']);
        static::assertTrue($data['channel']['configured']);
        static::assertSame('wx-appid', $data['channel']['paramFields'][0]['value']);
        static::assertNull($data['channel']['paramFields'][1]['value']);
        static::assertTrue($data['channel']['paramFields'][1]['configured']);
    }

    public function testResettingAnUnknownChannelIsRejected(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $this->expectExceptionObject(PaymentProviderException::unknownChannel('alipay_wap'));

        $controller->resetChannel('alipay', 'alipay_wap');
    }

    public function testResettingAChannelClearsItsConfiguration(): void
    {
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentProviderConfigCollection::class, [$this->storedProviderConfig()]),
            PaymentControllerFixture::repository(PaymentChannelConfigCollection::class, [$this->storedChannelConfig()]),
            PaymentControllerFixture::providerRegistry($this->provider())
        );

        $data = $this->decode($controller->resetChannel('alipay', 'alipay_h5'));

        static::assertSame('alipay_h5', $data['channel']['product']);
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
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write) => $write($connection));
        $account = new PaymentAccountEntity();
        $account->id = 'abcdef1234567890abcdef1234567890';
        $account->setUniqueIdentifier($account->id);
        $account->providerTechnicalName = 'alipay';
        $account->name = 'Merchant';
        $account->active = true;
        $account->enabledProducts = ['alipay_h5'];
        $account->productParams = ['alipay_h5' => PaymentControllerFixture::cipher()->encrypt(['appid' => 'wx-appid', 'sign_key' => 'channel-secret'])];
        $account->credentials = PaymentControllerFixture::cipher()->encrypt(['app_id' => 'platform-app', 'private_key' => 'sk']);

        $registry = $providerRegistry ?? PaymentControllerFixture::providerRegistry($this->provider());
        $accounts = PaymentControllerFixture::repository(PaymentAccountCollection::class, [$account]);
        $apps = PaymentControllerFixture::repository(PaymentAppCollection::class);

        $lock = static::createStub(PaymentAccountLock::class);
        $lock->method('lock')->willReturn($account);

        return new PaymentProviderConfigController(
            $providerRegistry ?? PaymentControllerFixture::providerRegistry($this->provider()),
            new PaymentProviderConfigService(
                $providerConfigRepository,
                $channelConfigRepository,
                $accounts,
                $apps,
                PaymentControllerFixture::repository(PaymentAppAccountCollection::class),
                PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
                $connection,
                new PaymentAccountService($accounts, $apps, $providerConfigRepository, $registry, PaymentControllerFixture::cipher(), $connection, $lock),
            ),
            PaymentControllerFixture::cipher(),
            new PaymentMethodRegistry([new PaymentMethod('alipay', 'payment.alipay')]),
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
                'alipay_h5' => new PaymentProduct(
                    'alipay_h5',
                    'alipay',
                    'Alipay H5',
                    [PaymentInteraction::REDIRECT],
                    'alipay',
                    ['CNY'],
                    [new PaymentScene('browser', 'web', 'browser'), new PaymentScene('browser', 'wap', 'browser'), new PaymentScene('browser', 'wap', 'wechat'), new PaymentScene('browser', 'wap', 'alipay')],
                    [
                        new PaymentConfigField('appid', 'appid', PaymentConfigField::TYPE_TEXT, true),
                        new PaymentConfigField('sign_key', 'sign_key', PaymentConfigField::TYPE_TEXT, true, true),
                    ],
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
        $config->accountId = 'abcdef1234567890abcdef1234567890';
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
