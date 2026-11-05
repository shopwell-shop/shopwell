<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\PaymentAppAccountDto;
use Shopwell\Core\System\Payment\App\PaymentAppChannelDto;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAppProvisioner::class)]
class PaymentAppProvisionerTest extends TestCase
{
    private PaymentAppEntity $app;

    private PaymentAccountEntity $account;

    /**
     * @var StaticEntityRepository<PaymentAppCollection>
     */
    private StaticEntityRepository $apps;

    /**
     * @var StaticEntityRepository<PaymentAppAccountCollection>
     */
    private StaticEntityRepository $grants;

    /**
     * @var StaticEntityRepository<PaymentAppChannelCollection>
     */
    private StaticEntityRepository $channels;

    /**
     * @var StaticEntityRepository<PaymentAccountCollection>
     */
    private StaticEntityRepository $accounts;

    private PaymentAppProvisioner $provisioner;

    protected function setUp(): void
    {
        $this->app = PaymentControllerFixture::app('client', 'old-secret');
        $this->account = new PaymentAccountEntity();
        $this->account->id = 'merchant-account';
        $this->account->setUniqueIdentifier($this->account->id);
        $this->account->providerTechnicalName = 'alipay';
        $this->account->name = 'Merchant';
        $this->account->ownerAppId = $this->app->id;
        $this->account->active = true;
        $this->account->credentials = PaymentControllerFixture::cipher()->encrypt(['app_id' => 'merchant', 'private_key' => 'keep-key']);
        $this->apps = new StaticEntityRepository(array_fill(0, 8, new PaymentAppCollection([$this->app])));
        $this->grants = new StaticEntityRepository(array_fill(0, 8, new PaymentAppAccountCollection()));
        $this->channels = new StaticEntityRepository(array_fill(0, 8, new PaymentAppChannelCollection()));
        $this->accounts = new StaticEntityRepository(array_fill(0, 8, new PaymentAccountCollection([$this->account])));
        $registry = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write) => $write($connection));
        $this->provisioner = new PaymentAppProvisioner($this->apps, $this->grants, $this->channels, PaymentControllerFixture::cipher(), $registry, new PaymentProductRegistry($registry), new MockClock('2026-11-05'), $connection, $this->accounts);
    }

    public function testNewAppReceivesAGeneratedSealedSecret(): void
    {
        $result = $this->provisioner->save(new PaymentAppDto(technicalName: '  client  ', name: 'Client', active: true, allowedIps: ['10.0.0.1', '']));
        $write = $this->apps->upserts[0][0];
        static::assertSame('client', $write['technicalName']);
        static::assertSame(PaymentAppEntity::TYPE_EXTERNAL, $write['type']);
        static::assertSame(['10.0.0.1'], $write['allowedIps']);
        static::assertTrue($result['generated']);
        static::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['secret']);
        static::assertSame($result['secret'], PaymentControllerFixture::cipher()->decryptValue($write['appSecret']));
    }

    public function testMissingTechnicalNameIsRejectedBeforeWriting(): void
    {
        try {
            $this->provisioner->save(new PaymentAppDto(name: 'Client'));
            static::fail('A technical name is required.');
        } catch (PaymentException $exception) {
            static::assertEquals(PaymentException::missingParameter('technical_name'), $exception);
            static::assertSame([], $this->apps->upserts);
        }
    }

    public function testEditingWithoutSecretKeepsExistingAuthenticationKeys(): void
    {
        $result = $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client'));
        static::assertSame('', $result['secret']);
        static::assertFalse($result['generated']);
        static::assertArrayNotHasKey('appSecret', $this->apps->upserts[0][0]);
    }

    public function testTypedSecretRotatesTheKeyAndPreservesGraceWindow(): void
    {
        $result = $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client', appSecret: ' new-secret '));
        $write = $this->apps->upserts[0][0];
        static::assertSame('new-secret', $result['secret']);
        static::assertSame('new-secret', PaymentControllerFixture::cipher()->decryptValue($write['appSecret']));
        static::assertSame($this->app->appSecret, $write['previousAppSecret']);
        static::assertInstanceOf(\DateTimeImmutable::class, $write['secretRotatedAt']);
        static::assertSame('2026-11-05', $write['secretRotatedAt']->format('Y-m-d'));
    }

    public function testNewAccountRequiresAKnownProvider(): void
    {
        $this->account->providerTechnicalName = 'missing';
        $this->expectExceptionObject(PaymentProviderException::unknownProvider('missing'));
        $this->provisioner->save(new PaymentAppDto(technicalName: 'client', accounts: [new PaymentAppAccountDto(accountId: $this->account->id)]));
    }

    public function testNewAccountWithoutProviderIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('account_id'));
        $this->provisioner->save(new PaymentAppDto(technicalName: 'client', accounts: [new PaymentAppAccountDto()]));
    }

    public function testAnExplicitAccountGrantDoesNotDuplicateCredentials(): void
    {
        $this->provisioner->save(new PaymentAppDto(technicalName: 'client', accounts: [new PaymentAppAccountDto(accountId: $this->account->id, enabled: true, position: 7)]));
        static::assertSame([], $this->accounts->upserts);
        $grant = $this->grants->upserts[0][0];
        static::assertSame($this->account->id, $grant['accountId']);
        static::assertArrayNotHasKey('providerTechnicalName', $grant);
        static::assertSame(7, $grant['position']);
        static::assertArrayNotHasKey('credentials', $grant);
    }

    public function testBorrowedAccountMayBeGrantedWithoutChangingItsKeys(): void
    {
        $this->account->ownerAppId = null;
        $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client', accounts: [new PaymentAppAccountDto(accountId: $this->account->id, enabled: true)]));
        static::assertSame([], $this->accounts->upserts);
        static::assertSame($this->account->id, $this->grants->upserts[0][0]['accountId']);
    }

    public function testMissingExplicitAccountFailsInsteadOfCreatingAnAlias(): void
    {
        $this->accounts->searches = [new PaymentAccountCollection()];
        $this->expectExceptionObject(PaymentProviderException::accountNotFound('missing'));
        $this->provisioner->save(new PaymentAppDto(technicalName: 'client', accounts: [new PaymentAppAccountDto(accountId: 'missing')]));
    }

    public function testAProductRequiresAnEnabledProviderAccountGrant(): void
    {
        $this->expectExceptionObject(PaymentProviderException::providerNotEnabled('alipay', $this->app->id));
        $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client', channels: [new PaymentAppChannelDto(channelTechnicalName: 'alipay_h5', enabled: true)]));
    }

    public function testDisabledProductsCanBeSavedWithoutAccountGrants(): void
    {
        $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client', accounts: [], channels: [new PaymentAppChannelDto(channelTechnicalName: 'alipay_h5', enabled: false)]));
        static::assertFalse($this->channels->upserts[0][0]['enabled']);
        static::assertSame([], $this->accounts->upserts);
    }

    public function testOmittedAccountListPreservesGrants(): void
    {
        $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client'));
        static::assertSame([], $this->grants->upserts);
        static::assertSame([], $this->grants->deletes);
    }

    public function testRevokingGrantRetainsMerchantCredentials(): void
    {
        $grant = new PaymentAppAccountEntity();
        $grant->id = 'grant';
        $grant->setUniqueIdentifier('grant');
        $grant->accountId = $this->account->id;
        $this->grants->searches = [new PaymentAppAccountCollection([$grant]), new PaymentAppAccountCollection()];
        $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'client', accounts: []));
        static::assertSame([[['id' => 'grant']]], $this->grants->deletes);
        static::assertSame([], $this->accounts->deletes);
    }

    public function testPlatformTenantCannotBeRenamed(): void
    {
        $this->app->type = PaymentAppEntity::TYPE_PLATFORM;
        $this->app->technicalName = PaymentAppEntity::PLATFORM_TECHNICAL_NAME;
        $this->provisioner->save(new PaymentAppDto(id: $this->app->id, technicalName: 'renamed'));
        static::assertSame(PaymentAppEntity::PLATFORM_TECHNICAL_NAME, $this->apps->upserts[0][0]['technicalName']);
    }

    public function testPlatformTenantCannotBeDeleted(): void
    {
        $this->app->type = PaymentAppEntity::TYPE_PLATFORM;
        $this->expectExceptionObject(PaymentAppException::platformTenantNotDeletable($this->app->technicalName));
        $this->provisioner->delete($this->app->id, Context::createDefaultContext());
    }

    public function testExternalTenantCanBeDeleted(): void
    {
        $this->provisioner->delete($this->app->id, Context::createDefaultContext());
        static::assertSame([[['id' => $this->app->id]]], $this->apps->deletes);
    }
}
