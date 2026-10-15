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
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;

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
                static::assertTrue($row['active']);
                static::assertSame('https://app.example.com/notify', $row['notifyUrl']);
                static::assertNull($row['returnUrl']);
                static::assertSame(['10.0.0.1', '10.0.0.2'], $row['allowedIps']);
                static::assertSame(60, $row['rateLimit']);
                static::assertArrayHasKey('appSecret', $row);

                return true;
            }));

        $result = $this->provisioner($appRepository)->save([
            'technicalName' => '  client-a  ',
            'name' => 'Client A',
            'active' => true,
            'notifyUrl' => 'https://app.example.com/notify',
            'returnUrl' => '',
            'allowedIps' => ['10.0.0.1', '', '10.0.0.2'],
            'rateLimit' => '60',
        ]);

        static::assertSame($stored, $result['app']);
        static::assertTrue($result['generated']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['secret']);
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

                return true;
            }));

        $result = $this->provisioner($appRepository)->save([
            'id' => $existing->id,
            'technicalName' => 'client-a',
        ]);

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

        $result = $this->provisioner($appRepository)->save([
            'id' => $existing->id,
            'technicalName' => 'client-a',
            'appSecret' => '  my-own-secret  ',
        ]);

        static::assertSame('my-own-secret', $result['secret']);
        static::assertFalse($result['generated']);
    }

    public function testSavingWithoutATechnicalNameIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('technicalName'));

        $this->provisioner(PaymentControllerFixture::repository(PaymentAppCollection::class))
            ->save(['name' => 'Client A']);
    }

    public function testAnUnknownProviderIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');

        $this->expectExceptionObject(PaymentException::unknownProvider('stripe'));

        $this->provisioner(PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]))
            ->save([
                'technicalName' => 'client-a',
                'providers' => [['providerTechnicalName' => 'stripe']],
            ]);
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
        )->save([
            'id' => $stored->id,
            'technicalName' => 'client-a',
            'channels' => [['channelTechnicalName' => 'alipay_h5', 'enabled' => true]],
        ]);
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

                return true;
            }));

        $registry = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));

        $result = $this->provisioner(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            $appProviderRepository,
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $registry
        )->save([
            'id' => $stored->id,
            'technicalName' => 'client-a',
            'providers' => [['providerTechnicalName' => 'alipay', 'enabled' => true]],
        ]);

        static::assertSame($stored, $result['app']);
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
        );
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
