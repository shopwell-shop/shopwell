<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Config;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderConfigService::class)]
class PaymentProviderConfigServiceTest extends TestCase
{
    private PaymentProviderConfigEntity $config;

    private PaymentAccountEntity $account;

    /**
     * @var StaticEntityRepository<PaymentProviderConfigCollection>
     */
    private StaticEntityRepository $configs;

    /**
     * @var StaticEntityRepository<PaymentChannelConfigCollection>
     */
    private StaticEntityRepository $channels;

    /**
     * @var StaticEntityRepository<PaymentAccountCollection>
     */
    private StaticEntityRepository $accounts;

    private PaymentProviderConfigService $service;

    protected function setUp(): void
    {
        $this->config = new PaymentProviderConfigEntity();
        $this->config->id = 'platform-config';
        $this->config->setUniqueIdentifier($this->config->id);
        $this->config->providerTechnicalName = 'alipay';
        $this->config->accountId = '1234567890abcdef1234567890abcdef';
        $this->config->enabled = true;
        $this->config->settings = ['sandbox' => '1'];
        $this->account = new PaymentAccountEntity();
        $this->account->id = $this->config->accountId;
        $this->account->setUniqueIdentifier($this->account->id);
        $this->account->providerTechnicalName = 'alipay';
        $this->account->name = 'Merchant';
        $this->account->active = true;
        $this->account->credentials = PaymentControllerFixture::cipher()->encrypt(['app_id' => 'merchant-app', 'private_key' => 'keep-key']);
        $this->configs = new StaticEntityRepository(array_fill(0, 16, new PaymentProviderConfigCollection([$this->config])));
        $this->channels = new StaticEntityRepository(array_fill(0, 16, new PaymentChannelConfigCollection()));
        $this->accounts = new StaticEntityRepository(array_fill(0, 16, new PaymentAccountCollection([$this->account])));
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write) => $write($connection));
        $lock = static::createStub(PaymentAccountLock::class);
        $lock->method('lock')->willReturn($this->account);
        $this->service = new PaymentProviderConfigService(
            $this->configs,
            $this->channels,
            $this->accounts,
            new StaticEntityRepository(array_fill(0, 16, new PaymentAppCollection())),
            new StaticEntityRepository(array_fill(0, 16, new PaymentAppAccountCollection())),
            new StaticEntityRepository(array_fill(0, 16, new PaymentAppChannelCollection())),
            $connection,
            new PaymentAccountService($this->accounts, new StaticEntityRepository(array_fill(0, 16, new PaymentAppCollection())), $this->configs, PaymentControllerFixture::providerRegistry($this->provider()), PaymentControllerFixture::cipher(), $connection, $lock),
        );
    }

    public function testCredentialRotationKeepsExplicitAccountAndUnchangedSecret(): void
    {
        $this->service->saveProvider($this->provider(), true, ['app_id' => 'new-app', 'private_key' => ''], ['theme' => 'blue']);
        $account = $this->accounts->upserts[0][0];
        static::assertSame('1234567890abcdef1234567890abcdef', $account['id']);
        static::assertSame(['app_id' => 'new-app', 'private_key' => 'keep-key'], PaymentControllerFixture::cipher()->decrypt($account['credentials']));
        static::assertSame(['sandbox' => '1', 'theme' => 'blue'], $account['settings']);
        static::assertSame('1234567890abcdef1234567890abcdef', $this->configs->upserts[0][0]['accountId']);
        static::assertArrayNotHasKey('credentials', $this->configs->upserts[0][0]);
    }

    public function testEnablingAnIncompleteProviderFailsBeforeWritingAccount(): void
    {
        $this->configs->searches = [new PaymentProviderConfigCollection()];
        try {
            $this->service->saveProvider($this->provider(), true, ['private_key' => 'key'], null);
            static::fail('An app id is required.');
        } catch (PaymentGatewayException $exception) {
            static::assertEquals(PaymentGatewayException::missingCredentials('alipay', 'app_id'), $exception);
            static::assertSame([], $this->accounts->upserts);
        }
    }

    public function testDisabledProviderCanBeSavedBeforeCredentialsAreAvailable(): void
    {
        $this->configs->searches = [new PaymentProviderConfigCollection()];
        $this->service->saveProvider($this->provider(), false, [], null);
        static::assertFalse($this->accounts->upserts[0][0]['active']);
        static::assertSame('alipay', $this->accounts->upserts[0][0]['name']);
        static::assertTrue(\is_string($this->configs->upserts[0][0]['accountId']) && $this->configs->upserts[0][0]['accountId'] !== '');
    }

    public function testResetDisablesAdmissionAndRetainsHistoricalAccountAndKeys(): void
    {
        $this->service->resetProvider('alipay');
        static::assertFalse($this->accounts->upserts[0][0]['active']);
        static::assertSame('Merchant', $this->accounts->upserts[0][0]['name']);
        static::assertSame([[['id' => 'platform-config', 'enabled' => false]]], $this->configs->updates);
        static::assertSame([], $this->accounts->deletes);
        static::assertSame([], $this->configs->deletes);
    }

    public function testResettingAnUnconfiguredProviderIsHarmless(): void
    {
        $this->configs->searches = [new PaymentProviderConfigCollection()];
        $this->service->resetProvider('alipay');
        static::assertSame([], $this->accounts->updates);
        static::assertSame([], $this->configs->updates);
    }

    public function testSavingProductParametersPreservesAnOmittedSecretAndUpdatesTheAccount(): void
    {
        $stored = new PaymentChannelConfigEntity();
        $stored->id = 'product-config';
        $stored->setUniqueIdentifier($stored->id);
        $this->account->productParams = ['alipay_h5' => PaymentControllerFixture::cipher()->encrypt(['appid' => 'old-app', 'sign_key' => 'keep-secret'])];
        $this->channels->searches = [new PaymentChannelConfigCollection([$stored])];
        $this->service->saveChannel($this->product(), true, ['appid' => 'new-app', 'sign_key' => ''], []);
        $encrypted = $this->accounts->upserts[0][0]['productParams']['alipay_h5'];
        static::assertSame(['appid' => 'new-app', 'sign_key' => 'keep-secret'], PaymentControllerFixture::cipher()->decrypt($encrypted));
        static::assertArrayNotHasKey('params', $this->channels->upserts[0][0]);
        static::assertSame(['alipay_h5'], $this->accounts->upserts[0][0]['enabledProducts']);
    }

    public function testEnablingAProductRequiresItsDeclaredParameters(): void
    {
        $this->expectExceptionObject(PaymentGatewayException::missingCredentials('alipay_h5', 'appid'));
        $this->service->saveChannel($this->product(), true, [], []);
    }

    public function testResettingProductAdmissionRetainsAccountParameters(): void
    {
        $stored = new PaymentChannelConfigEntity();
        $stored->id = 'product-config';
        $stored->setUniqueIdentifier($stored->id);
        $this->channels->searches = [new PaymentChannelConfigCollection([$stored])];
        $this->service->resetChannel('alipay', 'alipay_h5');
        static::assertSame([[['id' => 'product-config']]], $this->channels->deletes);
        static::assertSame([], $this->accounts->upserts[0][0]['enabledProducts']);
    }

    private function provider(): PaymentProvider
    {
        return new PaymentProvider('alipay', 'Alipay', [new PaymentConfigField('app_id', 'App id', required: true), new PaymentConfigField('private_key', 'Key', required: true, secret: true)], ['alipay_h5' => $this->product()], static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class));
    }

    private function product(): PaymentProduct
    {
        return new PaymentProduct('alipay_h5', 'alipay', 'Alipay H5', [PaymentInteraction::REDIRECT], 'alipay', params: [new PaymentConfigField('appid', 'App id', required: true), new PaymentConfigField('sign_key', 'Key', required: true, secret: true)]);
    }
}
