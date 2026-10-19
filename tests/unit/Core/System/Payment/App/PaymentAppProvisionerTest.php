<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\PaymentAppChannelDto;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProviderDto;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAppProvisioner::class)]
class PaymentAppProvisionerTest extends TestCase
{
    public function testANewAppIsCreatedWithAGeneratedSecretThatIsHandedOutOnce(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');

        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($stored));
        $appRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data): bool {
                $row = $data[0];

                static::assertSame('client-a', $row['technicalName']);
                static::assertSame('Client A', $row['name']);
                // an app onboarded here is always a caller of the api, never the tenant of the shop
                static::assertSame(PaymentAppEntity::TYPE_EXTERNAL, $row['type']);
                static::assertTrue($row['active']);
                static::assertSame('https://app.example.com/notify', $row['notifyUrl']);
                static::assertNull($row['returnUrl']);
                static::assertSame(['10.0.0.1', '10.0.0.2'], $row['allowedIps']);
                static::assertSame(60, $row['rateLimit']);
                static::assertArrayHasKey('appSecret', $row);

                return true;
            }));

        $result = $this->provisioner($appRepository)->save(new PaymentAppDto(
            technicalName: '  client-a  ',
            name: 'Client A',
            active: true,
            notifyUrl: 'https://app.example.com/notify',
            returnUrl: '',
            allowedIps: ['10.0.0.1', '', '10.0.0.2'],
            rateLimit: 60,
        ));

        static::assertSame($stored, $result['app']);
        static::assertTrue($result['generated']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['secret']);
    }

    /**
     * A missing technical name must not be written as an empty one: the app would then exist without
     * a name anybody could address it by.
     */
    public function testAnAppWithoutATechnicalNameIsRejected(): void
    {
        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->expects($this->never())->method('upsert');

        $this->expectExceptionObject(PaymentException::missingParameter('technical_name'));

        $this->provisioner($appRepository)->save(new PaymentAppDto(name: 'Client A'));
    }

    /**
     * An authorization without an interface must not be looked up as the empty provider name either.
     */
    public function testAnAuthorizationWithoutAnInterfaceIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($stored));

        $this->expectExceptionObject(PaymentException::missingParameter('provider_technical_name'));

        $this->provisioner($appRepository)->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto()],
        ));
    }

    public function testUpdatingAnAppWithoutATypedSecretKeepsTheStoredOne(): void
    {
        $existing = PaymentControllerFixture::app('client-a', 'stored-secret');

        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($existing));
        $appRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data) use ($existing): bool {
                static::assertSame($existing->id, $data[0]['id']);
                static::assertArrayNotHasKey('appSecret', $data[0]);
                // the kind of an app never changes through this endpoint - a stored platform tenant
                // must not become an external app by being saved again
                static::assertArrayNotHasKey('type', $data[0]);

                return true;
            }));

        $result = $this->provisioner($appRepository)->save(new PaymentAppDto(
            id: $existing->id,
            technicalName: 'client-a',
        ));

        static::assertSame('', $result['secret']);
        static::assertFalse($result['generated']);
    }

    public function testATypedSecretIsSealedAndHandedOutPlain(): void
    {
        $existing = PaymentControllerFixture::app('client-a', 'stored-secret');

        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($existing));
        $appRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('my-own-secret', PaymentControllerFixture::cipher()->decryptValue($data[0]['appSecret']));

                return true;
            }));

        $result = $this->provisioner($appRepository)->save(new PaymentAppDto(
            id: $existing->id,
            technicalName: 'client-a',
            appSecret: '  my-own-secret  ',
        ));

        static::assertSame('my-own-secret', $result['secret']);
        static::assertFalse($result['generated']);
    }

    public function testAnUnknownProviderIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');

        $this->expectExceptionObject(PaymentException::unknownProvider('stripe'));

        $this->provisioner(PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]))
            ->save(new PaymentAppDto(
                technicalName: 'client-a',
                providers: [new PaymentAppProviderDto(providerTechnicalName: 'stripe')],
            ));
    }

    public function testAChannelWhoseProviderIsNotAuthorizedIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $registry = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));

        $this->expectExceptionObject(PaymentException::providerNotEnabled('alipay', $stored->id));

        $this->provisioner(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            PaymentControllerFixture::repository(PaymentAppProviderCollection::class),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $registry
        )->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            channels: [new PaymentAppChannelDto(channelTechnicalName: 'alipay_h5', enabled: true)],
        ));
    }

    public function testProvidersAreSyncedAndTheOnesMissingFromThePayloadAreRemoved(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $stale = PaymentControllerFixture::appProvider($stored->id);
        $stale->providerTechnicalName = 'paypal';

        $appProviderRepository = static::createMock(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_provider',
            1,
            new PaymentAppProviderCollection([$stale]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $appProviderRepository->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $data): bool => $data === [['id' => $stale->id]]));
        $appProviderRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data) use ($stored): bool {
                $row = $data[0];

                static::assertSame($stored->id, $row['paymentAppId']);
                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertTrue($row['enabled']);
                // no account source: the app collects on its own merchant account
                static::assertNull($row['sourceAppId']);

                return true;
            }));

        $registry = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));

        $result = $this->provisioner(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            $appProviderRepository,
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $registry
        )->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto(providerTechnicalName: 'alipay', enabled: true)],
        ));

        static::assertSame($stored, $result['app']);
    }

    /**
     * The account source is an app: pointing at the tenant of the platform is what "the platform
     * collects for me" means, and it stores the app id instead of a mode.
     */
    public function testAnAuthorizationMayInheritTheAccountOfAnotherApp(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $platform = $this->platformTenant();

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === [$platform->id]
                ? new EntitySearchResult('payment_app', 1, new PaymentAppCollection([$platform]), null, new Criteria(), Context::createDefaultContext())
                : new EntitySearchResult('payment_app', 1, new PaymentAppCollection([$stored]), null, new Criteria(), Context::createDefaultContext())
        );

        $appProviderRepository = static::createMock(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_provider',
            0,
            new PaymentAppProviderCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $appProviderRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data) use ($platform): bool {
                static::assertSame($platform->id, $data[0]['sourceAppId']);
                // an inheriting authorization carries no credentials of its own
                static::assertArrayNotHasKey('credentials', $data[0]);

                return true;
            }));

        $this->provisioner($appRepository, $appProviderRepository, null, $this->alipayRegistry())->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto(
                providerTechnicalName: 'alipay',
                enabled: true,
                sourceAppId: $platform->id,
            )],
        ));
    }

    /**
     * An app that inherits from itself has no account at all - the shortest circle is refused where
     * the merchant can still fix it.
     */
    public function testAnAuthorizationThatInheritsFromItselfIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');

        $this->expectExceptionObject(PaymentException::providerInheritanceCycle('alipay', 'client-a'));

        $this->provisioner(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            null,
            null,
            $this->alipayRegistry()
        )->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto(
                providerTechnicalName: 'alipay',
                enabled: true,
                sourceAppId: $stored->id,
            )],
        ));
    }

    /**
     * A source app that does not exist would be a chain without a merchant account, so the save is
     * refused instead of storing an id nothing can resolve.
     */
    public function testAnAccountSourceThatDoesNotExistIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === [$stored->id]
                ? new EntitySearchResult('payment_app', 1, new PaymentAppCollection([$stored]), null, new Criteria(), Context::createDefaultContext())
                : new EntitySearchResult('payment_app', 0, new PaymentAppCollection([]), null, new Criteria(), Context::createDefaultContext())
        );

        $this->expectExceptionObject(PaymentException::providerSourceAppMissing('alipay', 'client-a', 'unknown-app'));

        $this->provisioner($appRepository, null, null, $this->alipayRegistry())->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto(
                providerTechnicalName: 'alipay',
                enabled: true,
                sourceAppId: 'unknown-app',
            )],
        ));
    }

    /**
     * The tenant of the platform is seeded by the migration and its secret is the one the platform
     * signs its own notifications with - naming its id must not be enough to rewrite it.
     */
    public function testSavingThePlatformTenantIsRejected(): void
    {
        $platform = $this->platformTenant();

        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($platform));
        $appRepository->expects($this->never())->method('upsert');

        $this->expectExceptionObject(PaymentException::platformTenantReadOnly(PaymentAppEntity::PLATFORM_TECHNICAL_NAME));

        $this->provisioner($appRepository)->save(new PaymentAppDto(
            id: $platform->id,
            technicalName: PaymentAppEntity::PLATFORM_TECHNICAL_NAME,
            name: 'Renamed',
            active: false,
            notifyUrl: 'https://evil.example.com/notify',
        ));
    }

    public function testDeletingThePlatformTenantIsRejected(): void
    {
        $platform = $this->platformTenant();

        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($platform));
        $appRepository->expects($this->never())->method('delete');

        $this->expectExceptionObject(PaymentException::platformTenantReadOnly(PaymentAppEntity::PLATFORM_TECHNICAL_NAME));

        $this->provisioner($appRepository)->delete($platform->id, Context::createDefaultContext());
    }

    public function testDeletingAnExternalAppRemovesTheRow(): void
    {
        $external = PaymentControllerFixture::app('client-a', 'stored-secret');

        $appRepository = static::createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($external));
        $appRepository->expects($this->once())
            ->method('delete')
            ->with(
                static::callback(static fn (array $data): bool => $data === [['id' => $external->id]]),
                static::anything()
            );

        $this->provisioner($appRepository)->delete($external->id, Context::createDefaultContext());
    }

    public function testSaveLeavesTheAuthorizationsUntouchedWhenNoProvidersAreGiven(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $provider = PaymentControllerFixture::appProvider($stored->id);
        $channel = PaymentControllerFixture::appChannel($stored->id);

        // revoking an authorization is a security relevant decision: a caller that does not send the
        // field must not revoke anything
        $appProviderRepository = static::createMock(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_provider',
            1,
            new PaymentAppProviderCollection([$provider]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $appProviderRepository->expects($this->never())->method('delete');
        $appProviderRepository->expects($this->never())->method('upsert');

        $appChannelRepository = static::createMock(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_channel',
            1,
            new PaymentAppChannelCollection([$channel]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $appChannelRepository->expects($this->never())->method('delete');
        $appChannelRepository->expects($this->never())->method('upsert');

        $result = $this->provisioner(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            $appProviderRepository,
            $appChannelRepository
        )->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
        ));

        static::assertSame($stored, $result['app']);
    }

    public function testSaveRevokesEveryAuthorizationWhenAnEmptyListIsGiven(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $provider = PaymentControllerFixture::appProvider($stored->id);
        $channel = PaymentControllerFixture::appChannel($stored->id);

        // an explicitly sent empty list is the way to revoke everything, and it is the only one
        $appProviderRepository = static::createMock(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_provider',
            1,
            new PaymentAppProviderCollection([$provider]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $appProviderRepository->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $data): bool => $data === [['id' => $provider->id]]));
        $appProviderRepository->expects($this->never())->method('upsert');

        $appChannelRepository = static::createMock(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_channel',
            1,
            new PaymentAppChannelCollection([$channel]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $appChannelRepository->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $data): bool => $data === [['id' => $channel->id]]));
        $appChannelRepository->expects($this->never())->method('upsert');

        $this->provisioner(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            $appProviderRepository,
            $appChannelRepository
        )->save(new PaymentAppDto(
            id: $stored->id,
            technicalName: 'client-a',
            providers: [],
            channels: [],
        ));
    }

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppProviderCollection> $appProviderRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     */
    private function provisioner(
        EntityRepository $appRepository,
        ?EntityRepository $appProviderRepository = null,
        ?EntityRepository $appChannelRepository = null,
        ?PaymentProviderRegistry $providerRegistry = null
    ): PaymentAppProvisioner {
        $registry = $providerRegistry ?? PaymentControllerFixture::providerRegistry();

        return new PaymentAppProvisioner(
            $appRepository,
            $appProviderRepository ?? PaymentControllerFixture::repository(PaymentAppProviderCollection::class),
            $appChannelRepository ?? PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            PaymentControllerFixture::cipher(),
            $registry,
            new PaymentChannelRegistry($registry),
            new NativeClock(),
        );
    }

    private function alipayRegistry(): PaymentProviderRegistry
    {
        return PaymentControllerFixture::providerRegistry(
            PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')])
        );
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

    /**
     * @return EntitySearchResult<PaymentAppCollection>
     */
    private function appResult(PaymentAppEntity $app): EntitySearchResult
    {
        return new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$app]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
