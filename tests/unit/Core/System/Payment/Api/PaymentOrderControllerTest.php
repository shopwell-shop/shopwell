<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentOrderController;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderController::class)]
class PaymentOrderControllerTest extends TestCase
{
    private const CREATE_PATH = '/payment-api/v1/payment-orders';

    private const ORDER_PATH = '/payment-api/v1/payment-orders/PG-1';

    public function testACreateWithoutCredentialsIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $this->expectExceptionObject(PaymentException::missingAuthParameters(['app_id']));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->create(new Request());
    }

    public function testACreateWithABodyThatIsNotJsonIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $request = new Request([], [], [], [], [], ['REQUEST_URI' => self::CREATE_PATH], 'not-a-json-body');

        $this->expectExceptionObject(PaymentException::missingParameter('body'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->create($request);
    }

    public function testACreateWithoutAnOutTradeNoIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::CREATE_PATH, [
            'channel' => 'alipay_h5',
            'amount' => 1000,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);

        $this->expectExceptionObject(PaymentException::missingParameter('out_trade_no'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->create($request);
    }

    public function testACreateWithAnUnknownChannelIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::CREATE_PATH, [
            'out_trade_no' => 'T-1',
            'channel' => 'unknown_channel',
            'amount' => 1000,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);

        $this->expectExceptionObject(PaymentException::unknownChannel('unknown_channel'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->create($request);
    }

    public function testACreateOfAnAlreadyPaidOutTradeNoIsAnsweredWithTheState(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['paidAt' => PaymentControllerFixture::clock()->now(), 'paidAmount' => 1000]);

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::CREATE_PATH, [
            'out_trade_no' => 'T-1',
            'channel' => 'alipay_h5',
            'amount' => 1000,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);

        $controller = $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), [
            'appProviders' => [PaymentControllerFixture::appProvider($app->id)],
            'appChannels' => [PaymentControllerFixture::appChannel($app->id)],
            'channelConfigs' => [PaymentControllerFixture::channelConfig()],
            'providerConfigs' => [PaymentControllerFixture::providerConfig()],
            'states' => ['paid'],
        ]);

        $data = $this->decode($controller->create($request));

        static::assertSame('PG-1', $data['payment_no']);
        static::assertSame('T-1', $data['out_trade_no']);
        static::assertSame('paid', $data['state']);
        static::assertSame(1000, $data['amount']);
        static::assertSame(1000, $data['paid_amount']);
        static::assertNull($data['pay']);
    }

    public function testAGetWithoutCredentialsIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $this->expectExceptionObject(PaymentException::missingAuthParameters(['app_id']));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->get(new Request(), 'PG-1');
    }

    public function testAGetOfAnUnknownOrderIsNotFound(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_GET, self::ORDER_PATH);

        $this->expectExceptionObject(PaymentException::orderNotFound('PG-1'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->get($request, 'PG-1');
    }

    public function testAGetOfAnOrderOfAnotherAppIsForbidden(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order('another-app');

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_GET, self::ORDER_PATH);

        $this->expectExceptionObject(PaymentException::foreignApp('client-a', 'PG-1'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]))->get($request, 'PG-1');
    }

    public function testAGetReturnsTheOrderOfTheApp(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['stateId' => 'state-opened']);

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_GET, self::ORDER_PATH);

        $controller = $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), [
            'states' => ['opened'],
        ]);

        $data = $this->decode($controller->get($request, 'PG-1'));

        static::assertSame('PG-1', $data['payment_no']);
        static::assertSame('T-1', $data['out_trade_no']);
        static::assertSame('alipay', $data['provider']);
        static::assertSame('alipay_h5', $data['channel']);
        static::assertSame('opened', $data['state']);
        static::assertSame('CNY', $data['currency']);
    }

    public function testACloseWithoutCredentialsIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $this->expectExceptionObject(PaymentException::missingAuthParameters(['app_id']));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class))->close(new Request(), 'PG-1');
    }

    public function testACloseClosesTheOrder(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        // the order the api hands over carries its app, which is what the channel configuration is
        // resolved from
        $order = PaymentControllerFixture::order($app->id, ['stateId' => 'state-closed', 'paymentApp' => $app]);

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::ORDER_PATH . '/close');

        $controller = $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), [
            'appProviders' => [PaymentControllerFixture::appProvider($app->id)],
            'appChannels' => [PaymentControllerFixture::appChannel($app->id)],
            'channelConfigs' => [PaymentControllerFixture::channelConfig()],
            'providerConfigs' => [PaymentControllerFixture::providerConfig()],
            'states' => ['closed'],
        ]);

        $data = $this->decode($controller->close($request, 'PG-1'));

        static::assertSame('closed', $data['state']);
        static::assertSame('PG-1', $data['payment_no']);
    }

    public function testACloseOfAnAlreadyClosedOrderIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['closedAt' => PaymentControllerFixture::clock()->now()]);

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::ORDER_PATH . '/close');

        $this->expectExceptionObject(PaymentException::orderAlreadyClosed('PG-1'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]))->close($request, 'PG-1');
    }

    /**
     * @param array{
     *     appProviders?: list<PaymentAppProviderEntity>,
     *     appChannels?: list<PaymentAppChannelEntity>,
     *     channelConfigs?: list<PaymentChannelConfigEntity>,
     *     providerConfigs?: list<PaymentProviderConfigEntity>,
     *     states?: list<string>
     * } $options
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     */
    private function controller(PaymentAppEntity $app, EntityRepository $orderRepository, array $options = []): PaymentOrderController
    {
        $providers = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider(
            'alipay',
            [PaymentControllerFixture::channel('alipay_h5', 'alipay')],
            gatewayFactory: PaymentControllerFixture::closingGatewayFactory(),
        ));
        $resolver = PaymentControllerFixture::configResolver(
            $providers,
            PaymentControllerFixture::channelRegistry($providers),
            $options['appProviders'] ?? [],
            $options['appChannels'] ?? [],
            $options['channelConfigs'] ?? [],
            $options['providerConfigs'] ?? [],
        );

        $orderService = PaymentControllerFixture::orderService($orderRepository, $resolver);

        $states = array_map(PaymentControllerFixture::stateName(...), $options['states'] ?? []);

        $transactions = PaymentControllerFixture::repository(PaymentTransactionCollection::class);

        return new PaymentOrderController(
            PaymentControllerFixture::authenticator([$app]),
            $orderService,
            PaymentControllerFixture::captureService($transactions, $resolver, $orderService),
            PaymentControllerFixture::closer($orderRepository, $transactions, $resolver, $orderService),
            PaymentControllerFixture::presenter($states),
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
