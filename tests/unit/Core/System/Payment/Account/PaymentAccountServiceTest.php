<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Account;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountDto;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAccountService::class)]
class PaymentAccountServiceTest extends TestCase
{
    private PaymentAccountEntity $account;

    /**
     * @var StaticEntityRepository<PaymentAccountCollection>
     */
    private StaticEntityRepository $accounts;

    /**
     * @var StaticEntityRepository<PaymentProviderConfigCollection>
     */
    private StaticEntityRepository $configs;

    private PaymentAccountService $service;

    protected function setUp(): void
    {
        $this->account = new PaymentAccountEntity();
        $this->account->id = '1234567890abcdef1234567890abcdef';
        $this->account->setUniqueIdentifier($this->account->id);
        $this->account->providerTechnicalName = 'plugin';
        $this->account->name = 'Original merchant';
        $this->account->active = true;
        $this->account->enabledProducts = ['plugin_capture'];
        $this->account->credentials = $this->cipher()->encrypt(['merchant' => 'original', 'key' => 'keep-key']);
        $this->account->productParams = ['plugin_capture' => $this->cipher()->encrypt(['application' => 'old-app', 'token' => 'keep-token'])];
        $this->accounts = new StaticEntityRepository(array_fill(0, 20, new PaymentAccountCollection([$this->account])));
        $this->configs = new StaticEntityRepository(array_fill(0, 20, new PaymentProviderConfigCollection()));
        $product = new PaymentProduct('plugin_capture', 'plugin', 'Capture', [PaymentInteraction::REDIRECT], 'plugin', params: [new PaymentConfigField('application', required: true), new PaymentConfigField('token', required: true, secret: true), new PaymentConfigField('optional_product'), new PaymentConfigField('product_mode', default: 'standard')]);
        $provider = new PaymentProvider('plugin', 'Plugin', [new PaymentConfigField('merchant', required: true), new PaymentConfigField('key', required: true, secret: true), new PaymentConfigField('sandbox', type: PaymentConfigField::TYPE_BOOL, default: '1'), new PaymentConfigField('optional')], [$product->technicalName => $product], static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class));
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write) => $write($connection));
        $lock = static::createStub(PaymentAccountLock::class);
        $lock->method('lock')->willReturn($this->account);
        $this->service = new PaymentAccountService($this->accounts, new StaticEntityRepository([new PaymentAppCollection()]), $this->configs, new PaymentProviderRegistry([$provider]), $this->cipher(), $connection, $lock);
    }

    public function testCreateStoresIndependentEncryptedAccountWithExplicitProducts(): void
    {
        $account = $this->service->save(new PaymentAccountDto('plugin', ' New merchant ', true, ['plugin_capture'], ['merchant' => 'new', 'key' => 'new-key'], ['plugin_capture' => ['application' => 'new-app', 'token' => 'new-token']]), Context::createDefaultContext());
        $write = $this->accounts->upserts[0][0];
        static::assertSame($account->id, $write['id']);
        static::assertSame('New merchant', $write['name']);
        static::assertSame(['plugin_capture'], $write['enabledProducts']);
        static::assertSame(['sandbox' => '1', 'merchant' => 'new', 'key' => 'new-key'], $this->cipher()->decrypt($write['credentials']));
        static::assertSame(['product_mode' => 'standard', 'application' => 'new-app', 'token' => 'new-token'], $this->cipher()->decrypt($write['productParams']['plugin_capture']));
        static::assertNull($write['ownerAppId']);
        static::assertSame([], $this->configs->upserts);
    }

    public function testEmptySecretsKeepCurrentValuesDuringPartialEdit(): void
    {
        $this->service->save(new PaymentAccountDto('plugin', 'Original merchant', true, ['plugin_capture'], ['key' => ''], ['plugin_capture' => ['application' => 'new-app', 'token' => null]]), Context::createDefaultContext(), $this->account->id);
        $write = $this->accounts->upserts[0][0];
        static::assertSame(['merchant' => 'original', 'key' => 'keep-key'], $this->cipher()->decrypt($write['credentials']));
        static::assertSame(['application' => 'new-app', 'token' => 'keep-token'], $this->cipher()->decrypt($write['productParams']['plugin_capture']));
    }

    public function testOrdinaryFieldsCanBeClearedWhileBlankSecretsAndOmittedFieldsAreRetained(): void
    {
        $this->account->credentials = $this->cipher()->encrypt(['merchant' => 'original', 'key' => 'keep-key', 'sandbox' => '1', 'optional' => 'clear-me']);
        $this->account->productParams = ['plugin_capture' => $this->cipher()->encrypt(['application' => 'old-app', 'token' => 'keep-token', 'optional_product' => 'clear-me'])];
        $this->service->save(new PaymentAccountDto('plugin', 'Original merchant', true, ['plugin_capture'], ['sandbox' => '0', 'optional' => '', 'key' => ''], ['plugin_capture' => ['optional_product' => '', 'token' => null]]), Context::createDefaultContext(), $this->account->id);
        $write = $this->accounts->upserts[0][0];
        static::assertSame(['merchant' => 'original', 'key' => 'keep-key', 'sandbox' => '0', 'optional' => ''], $this->cipher()->decrypt($write['credentials']));
        static::assertSame(['application' => 'old-app', 'token' => 'keep-token', 'optional_product' => ''], $this->cipher()->decrypt($write['productParams']['plugin_capture']));
    }

    public function testNewAccountAppliesDeclaredDefaultsForOmittedFields(): void
    {
        $account = $this->service->save(new PaymentAccountDto('plugin', 'New merchant'), Context::createDefaultContext());
        static::assertSame(['sandbox' => '1'], $this->cipher()->decrypt($account->credentials));
        static::assertSame(['product_mode' => 'standard'], $this->cipher()->decrypt($account->productParams['plugin_capture'] ?? null));
    }

    public function testExistingAccountDoesNotAcquireNewDefaultsDuringNameOnlyEdit(): void
    {
        $originalFingerprint = PaymentAccountLock::fingerprint($this->account);
        $account = $this->service->save(new PaymentAccountDto('plugin', 'Changed name', true, ['plugin_capture']), Context::createDefaultContext(), $this->account->id);
        static::assertSame($originalFingerprint, PaymentAccountLock::fingerprint($account));
        static::assertSame($this->account->credentials, $account->credentials);
        static::assertSame($this->account->productParams, $account->productParams);
        static::assertArrayNotHasKey('sandbox', $this->cipher()->decrypt($account->credentials));
        static::assertArrayNotHasKey('product_mode', $this->cipher()->decrypt($account->productParams['plugin_capture'] ?? null));
    }

    public function testNameOnlyEditRetainsNullConfigurationAndItsFingerprint(): void
    {
        $this->account->credentials = null;
        $this->account->productParams = null;
        $this->account->enabledProducts = [];
        $this->account->active = false;
        $account = $this->service->save(new PaymentAccountDto('plugin', 'Changed name'), Context::createDefaultContext(), $this->account->id);
        static::assertSame(PaymentAccountLock::fingerprint($this->account), PaymentAccountLock::fingerprint($account));
    }

    public function testExplicitProductValuesUnchangedByAnEditKeepTheirCiphertext(): void
    {
        $account = $this->service->save(new PaymentAccountDto('plugin', 'Changed name', true, ['plugin_capture'], ['key' => ''], ['plugin_capture' => ['application' => 'old-app', 'token' => '']]), Context::createDefaultContext(), $this->account->id);
        static::assertSame(PaymentAccountLock::fingerprint($this->account), PaymentAccountLock::fingerprint($account));
    }

    public function testEnabledProductRequiresParametersEvenWhenProductParamsAreOmitted(): void
    {
        $this->account->productParams = [];
        $this->expectExceptionObject(PaymentGatewayException::missingCredentials('plugin_capture', 'application'));
        $this->service->save(new PaymentAccountDto('plugin', 'Original merchant', true, ['plugin_capture']), Context::createDefaultContext(), $this->account->id);
    }

    public function testNewEnabledAccountCannotSkipRequiredProductParameters(): void
    {
        $this->expectExceptionObject(PaymentGatewayException::missingCredentials('plugin_capture', 'application'));
        $this->service->save(new PaymentAccountDto('plugin', 'New merchant', true, ['plugin_capture'], ['merchant' => 'new', 'key' => 'key']), Context::createDefaultContext());
    }

    public function testDisabledAccountCanBeSavedBeforeSecretsAndProductParametersExist(): void
    {
        $this->service->save(new PaymentAccountDto('plugin', 'Draft merchant', false, ['plugin_capture']), Context::createDefaultContext());
        static::assertFalse($this->accounts->upserts[0][0]['active']);
    }

    public function testOmittedOwnerMetadataIsRetainedDuringAccountEdit(): void
    {
        $this->account->ownerAppId = 'abcdef1234567890abcdef1234567890';
        $this->service->save(new PaymentAccountDto('plugin', 'Updated name', true, ['plugin_capture']), Context::createDefaultContext(), $this->account->id);
        static::assertSame($this->account->ownerAppId, $this->accounts->upserts[0][0]['ownerAppId']);
    }

    public function testExistingAccountProviderCannotBeChanged(): void
    {
        $this->account->providerTechnicalName = 'other-provider';
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($this->account->id));
        $this->service->save(new PaymentAccountDto('plugin', 'Merchant'), Context::createDefaultContext(), $this->account->id);
    }

    public function testUnknownProviderIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownProvider('missing'));
        $this->service->save(new PaymentAccountDto('missing', 'Merchant'), Context::createDefaultContext());
    }

    public function testProductMustBelongToTheAccountProvider(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownChannel('other_capture'));
        $this->service->save(new PaymentAccountDto('plugin', 'Merchant', enabledProducts: ['other_capture']), Context::createDefaultContext());
    }

    public function testUndeclaredCredentialFieldIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownConfigurationField('plugin', 'undeclared_secret'));
        $this->service->save(new PaymentAccountDto('plugin', 'Merchant', credentials: ['undeclared_secret' => 'secret']), Context::createDefaultContext());
    }

    public function testUndeclaredProductFieldIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownConfigurationField('plugin_capture', 'undeclared_secret'));
        $this->service->save(new PaymentAccountDto('plugin', 'Merchant', productParams: ['plugin_capture' => ['undeclared_secret' => 'secret']]), Context::createDefaultContext());
    }

    public function testAccountValuesExposeOnlyDeclaredNonsecretFields(): void
    {
        $this->account->credentials = $this->cipher()->encrypt(['merchant' => 'original', 'key' => 'keep-key', 'undeclared_secret' => 'legacy-secret']);
        $data = $this->service->read($this->account->id, Context::createDefaultContext());
        static::assertSame(['merchant' => 'original', 'key' => null], $data['credentials']);
        static::assertSame(['application' => 'old-app', 'token' => null], $data['productParams']['plugin_capture']);
        static::assertStringNotContainsString('keep-key', json_encode($data, \JSON_THROW_ON_ERROR));
        static::assertStringNotContainsString('legacy-secret', json_encode($data, \JSON_THROW_ON_ERROR));
    }

    public function testAppChoicesIncludeAllAccountsWithoutValuesOrSettings(): void
    {
        $config = new PaymentProviderConfigEntity();
        $config->id = 'config';
        $config->setUniqueIdentifier($config->id);
        $config->accountId = $this->account->id;
        $this->configs->searches = [new PaymentProviderConfigCollection([$config])];
        $this->account->settings = ['sensitive' => 'private-setting'];
        $data = $this->service->list(Context::createDefaultContext(), false);
        static::assertSame([['id' => $this->account->id, 'name' => 'Original merchant', 'providerTechnicalName' => 'plugin', 'active' => true, 'ownerAppId' => null, 'isDefault' => true]], $data);
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher('account-test-secret');
    }
}
