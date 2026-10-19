<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderConfigService::class)]
class PaymentProviderConfigServiceTest extends TestCase
{
    public function testSavingAProviderSealsTheCredentialsAndKeepsTheValuesThatWereNotSent(): void
    {
        $stored = $this->storedProviderConfig(['app_id' => 'old-app', 'private_key' => 'sk']);

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->providerConfigResult($stored));
        $repository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data) use ($stored): bool {
                $row = $data[0];

                static::assertSame($stored->id, $row['id']);
                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertTrue($row['enabled']);

                // the private key was not sent, so the stored one is kept
                $credentials = PaymentControllerFixture::cipher()->decrypt($row['credentials']);
                static::assertSame(['app_id' => 'new-app', 'private_key' => 'sk'], $credentials);

                static::assertSame(['sandbox' => '1', 'theme' => 'blue'], $row['settings']);

                return true;
            }));

        $this->service($repository)->saveProvider(
            $this->provider(),
            true,
            ['app_id' => 'new-app', 'private_key' => ''],
            ['theme' => 'blue']
        );
    }

    public function testEnablingAProviderWithoutItsCredentialsIsRejected(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->providerConfigResult());
        $repository->expects($this->never())->method('upsert');

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay', 'app_id'));

        $this->service($repository)->saveProvider($this->provider(), true, ['private_key' => 'sk'], null);
    }

    public function testSavingADisabledProviderDoesNotDemandTheCredentials(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->providerConfigResult());
        $repository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static fn (array $data): bool => $data[0]['enabled'] === false));

        $this->service($repository)->saveProvider($this->provider(), false, [], null);
    }

    public function testResettingAProviderDeletesTheStoredConfiguration(): void
    {
        $stored = $this->storedProviderConfig(['app_id' => 'platform-app']);

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->providerConfigResult($stored));
        $repository->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $data): bool => $data === [['id' => $stored->id]]));

        $this->service($repository)->resetProvider('alipay');
    }

    public function testResettingAnUnconfiguredProviderIsHarmless(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->providerConfigResult());
        $repository->expects($this->never())->method('delete');

        $this->service($repository)->resetProvider('alipay');
    }

    public function testSavingAChannelSealsItsParams(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->channelConfigResult());
        $repository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(function (array $data): bool {
                $row = $data[0];

                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertSame('alipay_h5', $row['channelTechnicalName']);
                static::assertTrue($row['enabled']);
                static::assertSame(['appid' => 'wx-appid'], PaymentControllerFixture::cipher()->decrypt($row['params']));

                return true;
            }));

        $channel = $this->provider()->channels['alipay_h5'];

        $this->service(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), $repository)
            ->saveChannel($channel, true, ['appid' => 'wx-appid'], null);
    }

    public function testEnablingAChannelWithoutItsParamsIsRejected(): void
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->channelConfigResult());

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay_h5', 'appid'));

        $this->service(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), $repository)
            ->saveChannel($this->provider()->channels['alipay_h5'], true, [], null);
    }

    public function testResettingAChannelDeletesItsConfiguration(): void
    {
        $stored = $this->storedChannelConfig();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->channelConfigResult($stored));
        $repository->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $data): bool => $data === [['id' => $stored->id]]));

        $this->service(PaymentControllerFixture::repository(PaymentProviderConfigCollection::class), $repository)
            ->resetChannel('alipay', 'alipay_h5');
    }

    /**
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    private function service(?EntityRepository $providerConfigRepository = null, ?EntityRepository $channelConfigRepository = null): PaymentProviderConfigService
    {
        return new PaymentProviderConfigService(
            $providerConfigRepository ?? PaymentControllerFixture::repository(PaymentProviderConfigCollection::class),
            $channelConfigRepository ?? PaymentControllerFixture::repository(PaymentChannelConfigCollection::class),
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

    /**
     * @param array<string, string> $credentials
     */
    private function storedProviderConfig(array $credentials): PaymentProviderConfigEntity
    {
        $config = new PaymentProviderConfigEntity();
        $config->id = Uuid::randomHex();
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = 'alipay';
        $config->enabled = true;
        $config->credentials = PaymentControllerFixture::cipher()->encrypt($credentials);
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
     * @return EntitySearchResult<PaymentProviderConfigCollection>
     */
    private function providerConfigResult(PaymentProviderConfigEntity ...$configs): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentProviderConfigEntity::ENTITY_NAME,
            \count($configs),
            new PaymentProviderConfigCollection($configs),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentChannelConfigCollection>
     */
    private function channelConfigResult(PaymentChannelConfigEntity ...$configs): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentChannelConfigEntity::ENTITY_NAME,
            \count($configs),
            new PaymentChannelConfigCollection($configs),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
