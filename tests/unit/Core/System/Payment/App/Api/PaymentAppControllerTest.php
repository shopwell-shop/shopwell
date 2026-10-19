<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\Api\PaymentAppController;
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
        $app->providers = ['alipay' => $this->providerAssignment($app->id)];
        $app->channels = ['alipay_h5' => $this->channelAssignment($app->id)];

        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [$app]));

        $data = $this->decode($controller->list());

        static::assertCount(1, $data['apps']);
        static::assertSame($app->id, $data['apps'][0]['id']);
        static::assertSame('client-a', $data['apps'][0]['technicalName']);
        static::assertTrue($data['apps'][0]['hasSecret']);
        static::assertSame([], $data['apps'][0]['allowedIps']);
        static::assertCount(1, $data['apps'][0]['providers']);
        static::assertSame('alipay', $data['apps'][0]['providers'][0]['providerTechnicalName']);
        static::assertSame(PaymentAppProviderEntity::MODE_PLATFORM, $data['apps'][0]['providers'][0]['mode']);
        static::assertFalse($data['apps'][0]['providers'][0]['hasCredentials']);
        static::assertCount(1, $data['apps'][0]['channels']);
        static::assertSame('alipay_h5', $data['apps'][0]['channels'][0]['channelTechnicalName']);
        static::assertFalse($data['apps'][0]['channels'][0]['hasParams']);
    }

    public function testTheListSkipsEntitiesThatAreNotApps(): void
    {
        $controller = $this->controller(PaymentControllerFixture::repository(PaymentAppCollection::class, [PaymentControllerFixture::providerConfig()]));

        $data = $this->decode($controller->list());

        static::assertSame([], $data['apps']);
    }

    /**
     * The tenant of the shop is seeded and cannot be edited, so it must not show up in the onboarding
     * list - an operator would only ever see a row he cannot change.
     */
    public function testTheListOnlyAsksForExternalApps(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->with(static::callback(function (Criteria $criteria): bool {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);

                $filter = $filters[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('type', $filter->getField());
                static::assertSame(PaymentAppEntity::TYPE_EXTERNAL, $filter->getValue());

                return true;
            }))
            ->willReturn(new EntitySearchResult(
                'payment_app',
                0,
                new PaymentAppCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        static::assertSame([], $this->decode($this->controller($repository)->list())['apps']);
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

        $this->expectExceptionObject(PaymentException::unknownProvider('stripe'));

        $controller->save(new PaymentAppDto(
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto(providerTechnicalName: 'stripe')],
        ));
    }

    public function testProvidersAndChannelsAreStoredAndPresented(): void
    {
        $appId = Uuid::randomHex();
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $stored->providers = ['alipay' => $this->providerAssignment($appId)];
        $stored->channels = ['alipay_h5' => $this->channelAssignment($appId)];

        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            PaymentControllerFixture::repository(PaymentAppProviderCollection::class, [$this->providerAssignment($appId)]),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class, [$this->channelAssignment($appId)]),
            $this->registry()
        );

        $data = $this->decode($controller->save(new PaymentAppDto(
            technicalName: 'client-a',
            providers: [new PaymentAppProviderDto(
                providerTechnicalName: 'alipay',
                enabled: true,
                mode: PaymentAppProviderEntity::MODE_OWN,
                settings: ['sandbox' => '1'],
            )],
            channels: [new PaymentAppChannelDto(
                channelTechnicalName: 'alipay_h5',
                enabled: true,
                settings: ['theme' => 'blue'],
            )],
        )));

        static::assertCount(1, $data['app']['providers']);
        static::assertSame('alipay', $data['app']['providers'][0]['providerTechnicalName']);
        static::assertCount(1, $data['app']['channels']);
        static::assertSame('alipay_h5', $data['app']['channels'][0]['channelTechnicalName']);
    }

    public function testAChannelWhoseProviderIsNotAuthorizedIsRejected(): void
    {
        $stored = PaymentControllerFixture::app('client-a', 'stored-secret');
        $controller = $this->controller(
            PaymentControllerFixture::repository(PaymentAppCollection::class, [$stored]),
            PaymentControllerFixture::repository(PaymentAppProviderCollection::class),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $this->registry()
        );

        $this->expectExceptionObject(PaymentException::providerNotEnabled('alipay', $stored->id));

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
            PaymentControllerFixture::repository(PaymentAppProviderCollection::class, [$this->providerAssignment($stored->id)]),
            PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
            $this->registry()
        );

        $this->expectExceptionObject(PaymentException::unknownChannel('alipay_wap'));

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

    private function providerAssignment(string $appId): PaymentAppProviderEntity
    {
        return PaymentControllerFixture::appProvider($appId);
    }

    private function channelAssignment(string $appId): PaymentAppChannelEntity
    {
        return PaymentControllerFixture::appChannel($appId);
    }

    private function registry(): PaymentProviderRegistry
    {
        return PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));
    }

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppProviderCollection>|null $appProviderRepository
     * @param EntityRepository<PaymentAppChannelCollection>|null $appChannelRepository
     */
    private function controller(
        EntityRepository $appRepository,
        ?EntityRepository $appProviderRepository = null,
        ?EntityRepository $appChannelRepository = null,
        ?PaymentProviderRegistry $providerRegistry = null
    ): PaymentAppController {
        $registry = $providerRegistry ?? PaymentControllerFixture::providerRegistry();

        return new PaymentAppController(
            $appRepository,
            new PaymentAppProvisioner(
                $appRepository,
                $appProviderRepository ?? PaymentControllerFixture::repository(PaymentAppProviderCollection::class),
                $appChannelRepository ?? PaymentControllerFixture::repository(PaymentAppChannelCollection::class),
                PaymentControllerFixture::cipher(),
                $registry,
                new PaymentChannelRegistry($registry),
                new NativeClock(),
            ),
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
