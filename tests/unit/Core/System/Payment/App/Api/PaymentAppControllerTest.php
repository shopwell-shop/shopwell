<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Api\PaymentAppController;
use Shopwell\Core\System\Payment\App\PaymentAppAccountDto;
use Shopwell\Core\System\Payment\App\PaymentAppChannelDto;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAppController::class)]
class PaymentAppControllerTest extends TestCase
{
    public function testTheListPresentsTheAppsWithTheirAssociations(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'stored-secret');
        $app->accounts = ['alipay' => $this->providerAssignment($app->id)];
        $app->channels = ['alipay_h5' => $this->channelAssignment($app->id)];

        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$app]));

        $data = $this->decode($controller->list());

        static::assertCount(1, $data['apps']);
        static::assertSame($app->id, $data['apps'][0]['id']);
        static::assertSame('client-a', $data['apps'][0]['technicalName']);
        // the administration has to tell the two kinds of row apart, otherwise it cannot render the
        // tenant of the platform read only
        static::assertSame(PaymentAppEntity::TYPE_EXTERNAL, $data['apps'][0]['type']);
        static::assertTrue($data['apps'][0]['hasSecret']);
        static::assertSame([], $data['apps'][0]['allowedIps']);
        static::assertCount(1, $data['apps'][0]['accounts']);
        static::assertSame(['accountId', 'enabled', 'position'], array_keys($data['apps'][0]['accounts'][0]));
        static::assertSame(md5('merchant-' . $app->id), $data['apps'][0]['accounts'][0]['accountId']);
        static::assertCount(1, $data['apps'][0]['channels']);
        static::assertSame('alipay_h5', $data['apps'][0]['channels'][0]['channelTechnicalName']);
        static::assertArrayNotHasKey('params', $data['apps'][0]['channels'][0]);
    }

    /**
     * An app that collects on its own account gets its ordinary values back, while a secret only keeps
     * its key and the configured state. The original secret never appears in the response.
     */
    public function testAnAppThatCollectsOnItsOwnAccountHidesSecretsAndEchoesOrdinaryValues(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'stored-secret');
        $app->accounts = ['alipay' => PaymentControllerFixture::appAccount($app->id, true, ['app_id' => 'client-app', 'private_key' => 'sk'])];

        $channel = PaymentControllerFixture::appChannel($app->id);
        $app->channels = ['alipay_h5' => $channel];

        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$app]));

        $data = $this->decode($controller->list());

        static::assertSame(['accountId', 'enabled', 'position'], array_keys($data['apps'][0]['accounts'][0]));
        static::assertArrayNotHasKey('credentials', $data['accounts'][0]);
        static::assertArrayNotHasKey('settings', $data['accounts'][0]);
        static::assertArrayNotHasKey('productParams', $data['accounts'][0]);
        static::assertStringNotContainsString('"private_key":"sk"', (string) json_encode($data));
        static::assertStringNotContainsString('channel-secret', (string) json_encode($data));
    }

    public function testSharedAccountMetadataExposesOwnershipAndSanitizesProductParameters(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'stored-secret');
        $grant = PaymentControllerFixture::appAccount($app->id, credentials: ['app_id' => 'platform-app', 'private_key' => 'platform-secret']);
        static::assertNotNull($grant->account);
        $grant->account->ownerAppId = null;
        $grant->account->productParams = ['alipay_h5' => PaymentControllerFixture::cipher()->encrypt(['appid' => 'merchant-app', 'sign_key' => 'product-secret'])];
        $app->accounts = [$grant->accountId => $grant];
        $data = $this->decode($this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$app]))->list());
        $account = $data['apps'][0]['accounts'][0];
        static::assertSame(['accountId', 'enabled', 'position'], array_keys($account));
        static::assertNull($data['accounts'][0]['ownerAppId']);
        static::assertArrayNotHasKey('credentials', $data['accounts'][0]);
        static::assertArrayNotHasKey('productParams', $data['accounts'][0]);
        static::assertStringNotContainsString('platform-secret', (string) json_encode($data));
        static::assertStringNotContainsString('product-secret', (string) json_encode($data));
    }

    /**
     * The tenant of the platform collects for the shop itself and is listed on purpose: a merchant has
     * to see through which account the platform is paid, otherwise the list looks like nothing could
     * collect at all. It is the first row because it is the one an operator looks for, and it is
     * presented like any other app - plus the type that makes the page render it read only.
     */
    public function testTheListPresentsThePlatformTenantFirstAndDoesNotFilterByType(): void
    {
        $external = PaymentControllerFixture::app('client-a', 'stored-secret');
        $platform = $this->platformTenant();

        $repository = PaymentControllerFixture::repository(PaymentAppCollection::class, [$external, $platform]);

        $data = $this->decode($this->controller($repository)->list());

        static::assertCount(2, $data['apps']);
        static::assertSame($platform->id, $data['apps'][0]['id']);
        static::assertSame(PaymentAppEntity::PLATFORM_TECHNICAL_NAME, $data['apps'][0]['technicalName']);
        static::assertSame(PaymentAppEntity::TYPE_PLATFORM, $data['apps'][0]['type']);
        static::assertSame($external->id, $data['apps'][1]['id']);
        static::assertSame(PaymentAppEntity::TYPE_EXTERNAL, $data['apps'][1]['type']);
    }

    public function testDeletingThePlatformTenantIsRejected(): void
    {
        $platform = $this->platformTenant();

        $repository = PaymentControllerFixture::repository(PaymentAppCollection::class, [$platform]);

        $controller = $this->controller($repository);

        $this->expectExceptionObject(PaymentAppException::platformTenantNotDeletable(PaymentAppEntity::PLATFORM_TECHNICAL_NAME));

        $controller->delete($platform->id);
    }

    public function testANewAppGetsAGeneratedSecretReturnedOnce(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]));

        $data = $this->decode($controller->save(new PaymentAppDto(
            technicalName: '  client-a  ',
            name: 'Client A',
            active: true,
            notifyUrl: 'https://app.example.com/notify',
            returnUrl: '',
            allowedIps: ['10.0.0.1', '', '10.0.0.2'],
            rateLimit: 60,
        )));

        static::assertSame($stored->id, $data['app']['id']);
        static::assertTrue($data['secretGenerated']);
        static::assertIsString($data['appSecret']);
        static::assertSame(64, \strlen($data['appSecret']));
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $data['appSecret']);
    }

    public function testUpdatingAnAppWithoutATypedSecretDoesNotReturnTheStoredOne(): void
    {
        $existing = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$existing]));

        $data = $this->decode($controller->save(new PaymentAppDto(
            id: $existing->id,
            technicalName: 'client-a',
        )));

        static::assertSame($existing->id, $data['app']['id']);
        static::assertArrayNotHasKey('appSecret', $data);
        static::assertArrayNotHasKey('secretGenerated', $data);
    }

    public function testATypedSecretIsReturnedAndNotMarkedAsGenerated(): void
    {
        $existing = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$existing]));

        $data = $this->decode($controller->save(new PaymentAppDto(
            id: $existing->id,
            technicalName: 'client-a',
            appSecret: '  my-own-secret  ',
        )));

        static::assertSame('my-own-secret', $data['appSecret']);
        static::assertFalse($data['secretGenerated']);
    }

    public function testAnUnknownProviderInThePayloadIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]));

        $this->expectExceptionObject(PaymentProviderException::accountNotFound('missing'));

        $controller->save(new PaymentAppDto(
            technicalName: 'client-a',
            accounts: [new PaymentAppAccountDto(accountId: 'missing')],
        ));
    }

    public function testProvidersAndChannelsAreStoredAndPresented(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $appId = $stored->id;
        $stored->accounts = ['alipay' => $this->providerAssignment($appId)];
        $stored->channels = ['alipay_h5' => $this->channelAssignment($appId)];

        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            PaymentControllerFixture::repository(PaymentAppAccountCollection::class, [$this->providerAssignment($appId)]),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class, [$this->channelAssignment($appId)]),
            $this->registry()
        );

        $data = $this->decode($controller->save(new PaymentAppDto(
            technicalName: 'client-a',
            accounts: [new PaymentAppAccountDto(
                accountId: md5('merchant-' . $appId),
                enabled: true,
            )],
            channels: [new PaymentAppChannelDto(
                channelTechnicalName: 'alipay_h5',
                enabled: true,
                settings: ['theme' => 'blue'],
            )],
        )));

        static::assertCount(1, $data['app']['accounts']);
        static::assertSame(md5('merchant-' . $appId), $data['app']['accounts'][0]['accountId']);
        static::assertCount(1, $data['app']['channels']);
        static::assertSame('alipay_h5', $data['app']['channels'][0]['channelTechnicalName']);
    }

    public function testAChannelWhoseProviderIsNotAuthorizedIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            PaymentControllerFixture::repository(PaymentAppAccountCollection::class),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $this->registry()
        );

        $this->expectExceptionObject(PaymentProviderException::providerNotEnabled('alipay', $stored->id));

        $controller->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            channels: [new PaymentAppChannelDto(channelTechnicalName: 'alipay_h5', enabled: true)],
        ));
    }

    public function testAnUnknownChannelIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            PaymentControllerFixture::repository(PaymentAppAccountCollection::class, [$this->providerAssignment($stored->id)]),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $this->registry()
        );

        $this->expectExceptionObject(PaymentProviderException::unknownChannel('alipay_wap'));

        $controller->save(new PaymentAppDto(
            technicalName: 'client-a',
            channels: [new PaymentAppChannelDto(channelTechnicalName: 'alipay_wap', enabled: true)],
        ));
    }

    public function testDeleteAnswersWithoutContent(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class));

        $response = $controller->delete(Uuid::randomHex());

        static::assertSame(JsonResponse::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function providerAssignment(string $appId): PaymentAppAccountEntity
    {
        return PaymentControllerFixture::appAccount($appId);
    }

    /**
     * The row the payment migration seeds: the tenant the platform itself collects through.
     */
    private function platformTenant(): PaymentAppEntity
    {
        $platform = PaymentControllerFixture::app(PaymentAppEntity::PLATFORM_TECHNICAL_NAME, 'platform-secret');
        $platform->name = 'Platform Payment App';
        $platform->type = PaymentAppEntity::TYPE_PLATFORM;

        return $platform;
    }

    private function channelAssignment(string $appId): PaymentAppChannelEntity
    {
        return PaymentControllerFixture::appChannel($appId);
    }

    private function registry(): PaymentProviderRegistry
    {
        $channel = new PaymentProduct(
            'alipay_h5',
            'alipay',
            'alipay_h5',
            [PaymentInteraction::REDIRECT],
            'alipay',
            params: [
                new PaymentConfigField('appid'),
                new PaymentConfigField('sign_key', secret: true),
            ],
        );

        return PaymentControllerFixture::providerRegistry(new PaymentProvider(
            'alipay',
            'alipay',
            [
                new PaymentConfigField('app_id'),
                new PaymentConfigField('private_key', secret: true),
            ],
            ['alipay_h5' => $channel],
            static::createStub(PaymentGatewayFactory::class),
            static::createStub(PaymentNotificationExtractorInterface::class),
        ));
    }

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppAccountCollection>|null $appProviderRepository
     * @param EntityRepository<PaymentAppChannelCollection>|null $appChannelRepository
     */
    private function controller(
        EntityRepository $appRepository,
        ?EntityRepository $appProviderRepository = null,
        ?EntityRepository $appChannelRepository = null,
        ?PaymentProviderRegistry $providerRegistry = null
    ): PaymentAppController {
        $registry = $providerRegistry ?? $this->registry();
        $channelRegistry = new PaymentProductRegistry($registry);
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write) => $write($connection));

        $accountEntities = [];
        foreach ($appRepository->search(new Criteria(), Context::createDefaultContext())->getEntities() as $app) {
            foreach ($app->accounts ?? [] as $grant) {
                if ($grant->account !== null) {
                    $accountEntities[$grant->accountId] = $grant->account;
                }
            }
        }
        $accounts = PaymentControllerFixture::repository(PaymentAccountCollection::class, array_values($accountEntities));

        return new PaymentAppController(
            $appRepository,
            new PaymentAppProvisioner(
                $appRepository,
                $appProviderRepository ?? PaymentControllerFixture::repository(PaymentAppAccountCollection::class),
                $appChannelRepository ?? PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
                PaymentControllerFixture::cipher(),
                $registry,
                $channelRegistry,
                new NativeClock(),
                $connection,
                $accounts,
            ),
            new PaymentAccountService($accounts, $appRepository, PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), $registry, PaymentControllerFixture::cipher(), $connection, static::createStub(PaymentAccountLock::class)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
