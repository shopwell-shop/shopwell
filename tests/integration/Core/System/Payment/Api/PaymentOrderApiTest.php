<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of `POST /payment-api/v1/payment-orders`, `GET /payment-api/v1/payment-orders/{paymentNo}`
 * and `POST /payment-api/v1/payment-orders/{paymentNo}/close`.
 *
 * @internal
 */
#[Package('framework')]
class PaymentOrderApiTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    private const PATH_ORDERS = '/payment-api/v1/payment-orders';

    /**
     * The keys the api answers for a payment order.
     */
    private const ORDER_KEYS = [
        'payment_no',
        'out_trade_no',
        'provider',
        'channel',
        'amount',
        'paid_amount',
        'refunded_amount',
        'currency',
        'subject',
        'attach',
        'state',
        'out_payment_no',
        'expire_at',
        'paid_at',
        'closed_at',
        'pay',
    ];

    /**
     * The default business parameters of a create call, `PG` payment numbers and `OT` trade numbers
     * keep the answers of the tests apart.
     */
    private const CREATE_DEFAULTS = [
        'channel' => 'alipay_h5',
        'amount' => 100,
        'currency' => 'CNY',
        'subject' => '测试商品',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAlipayPlatform();
        $this->createContractApp();
    }

    public function testCreateOrder(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'attach' => '附加数据',
        ] + self::CREATE_DEFAULTS);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $order = self::decodeResponse($response);
        foreach (self::ORDER_KEYS as $key) {
            static::assertArrayHasKey($key, $order, \sprintf('key "%s" is missing', $key));
        }

        static::assertMatchesRegularExpression('/^PG\d{14}[0-9A-F]{8}$/', (string) $order['payment_no']);
        static::assertSame($payload['out_trade_no'], $order['out_trade_no']);
        static::assertSame('alipay', $order['provider']);
        static::assertSame('alipay_h5', $order['channel']);
        static::assertSame(100, $order['amount']);
        // a fresh order was neither paid nor refunded
        static::assertSame(0, $order['paid_amount']);
        static::assertSame(0, $order['refunded_amount']);
        static::assertSame('CNY', $order['currency']);
        static::assertSame('测试商品', $order['subject']);
        static::assertSame('附加数据', $order['attach']);
        static::assertSame(PaymentOrderStates::STATE_OPENED, $order['state']);
        // the h5 channel is a redirect flow, the channel answers no trade number of its own yet
        static::assertNull($order['out_payment_no']);
        static::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) $order['expire_at']);
        static::assertNull($order['paid_at']);
        static::assertNull($order['closed_at']);

        // the redirect parameters the app sends the payer to the channel with
        static::assertIsArray($order['pay']);
        static::assertSame('redirect', $order['pay']['flow']);
        static::assertNotSame('', (string) $order['pay']['pay_url']);
        static::assertSame('GET', $order['pay']['http_method']);
    }

    public function testCreateOrderPersistsTheOrderAndItsCaptureAttempt(): void
    {
        $client = $this->getBrowser();

        $created = $this->createOrderViaApi($client);

        // the order is stored with its fresh state: nothing was paid or refunded on it yet
        $connection = static::getContainer()->get(Connection::class);
        $orderRow = $connection->fetchAssociative(
            'SELECT id, paid_amount, refunded_amount FROM payment_order WHERE payment_no = :paymentNo',
            ['paymentNo' => $created['payment_no']]
        );
        static::assertIsArray($orderRow);
        static::assertSame(0, (int) $orderRow['paid_amount']);
        static::assertSame(0, (int) $orderRow['refunded_amount']);

        // the create handed the order to the channel, the attempt is kept with request and answer
        $transaction = $connection->fetchAssociative(
            'SELECT * FROM payment_transaction WHERE payment_order_id = :orderId',
            ['orderId' => $orderRow['id']]
        );
        static::assertIsArray($transaction);
        static::assertMatchesRegularExpression('/^PT\d{14}[0-9A-F]{6}$/', (string) $transaction['transaction_no']);
        static::assertSame('capture', $transaction['type']);
        static::assertSame('success', $transaction['status']);
        static::assertSame('alipay', $transaction['provider_technical_name']);
        static::assertSame('alipay_h5', $transaction['channel_technical_name']);
        static::assertNotNull($transaction['request_payload']);
        static::assertNotNull($transaction['response_payload']);
    }

    public function testCreateMissingParameter(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_BAD_REQUEST, PaymentException::MISSING_PARAMETER);
    }

    public function testCreateWithZeroAmountRejected(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 0,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_BAD_REQUEST, PaymentException::INVALID_AMOUNT);
    }

    public function testCreateWithFractionalAmountRejected(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => '12.50',
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_BAD_REQUEST, PaymentException::INVALID_AMOUNT);
    }

    public function testCreateUnknownChannel(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'unknown_channel',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_NOT_FOUND, PaymentException::UNKNOWN_CHANNEL);
    }

    public function testCreateChannelNotEnabledForApp(): void
    {
        $client = $this->getBrowser();

        // alipay_web is declared by the interface, but the app only has alipay_h5 enabled
        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_web',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_FORBIDDEN, PaymentException::CHANNEL_NOT_ENABLED);
    }

    public function testCreateCurrencyNotSupported(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'USD',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_BAD_REQUEST, PaymentException::CURRENCY_NOT_SUPPORTED);
    }

    public function testCreateProviderNotAuthorized(): void
    {
        // an app without the authorization for the interface behind the channel
        $this->createContractApp(withProvider: false, withChannel: false);

        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_FORBIDDEN, PaymentException::PROVIDER_NOT_ENABLED);
    }

    public function testCreateIsIdempotentOnOutTradeNo(): void
    {
        $client = $this->getBrowser();

        $business = ['out_trade_no' => 'OT' . bin2hex(random_bytes(8))] + self::CREATE_DEFAULTS;

        $this->postJson($client, self::PATH_ORDERS, $this->signedPayload('POST', self::PATH_ORDERS, $business));
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $first = self::decodeResponse($response);

        // a retry is signed with a fresh nonce: a repeated nonce is a replay and rejected
        $this->postJson($client, self::PATH_ORDERS, $this->signedPayload('POST', self::PATH_ORDERS, $business));
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $second = self::decodeResponse($response);

        static::assertSame($business['out_trade_no'], $first['out_trade_no']);
        static::assertSame($first['payment_no'], $second['payment_no']);
        static::assertSame(PaymentOrderStates::STATE_OPENED, $second['state']);
        static::assertIsArray($second['pay']);
        static::assertSame('redirect', $second['pay']['flow']);

        // the retry answered the very same order, no second one was written
        $orders = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM payment_order WHERE out_trade_no = :outTradeNo',
            ['outTradeNo' => $business['out_trade_no']]
        );
        static::assertSame(1, (int) $orders);
    }

    public function testGetOrder(): void
    {
        $client = $this->getBrowser();

        $created = $this->createOrderViaApi($client, ['attach' => '附加数据']);
        $path = self::PATH_ORDERS . '/' . $created['payment_no'];

        $client->request('GET', $path, $this->signedPayload('GET', $path));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $order = self::decodeResponse($response);
        foreach (self::ORDER_KEYS as $key) {
            static::assertArrayHasKey($key, $order, \sprintf('key "%s" is missing', $key));
        }

        static::assertSame($created['payment_no'], $order['payment_no']);
        static::assertSame($created['out_trade_no'], $order['out_trade_no']);
        static::assertSame('alipay', $order['provider']);
        static::assertSame('alipay_h5', $order['channel']);
        static::assertSame(100, $order['amount']);
        static::assertSame(0, $order['paid_amount']);
        static::assertSame(0, $order['refunded_amount']);
        static::assertSame('CNY', $order['currency']);
        static::assertSame('测试商品', $order['subject']);
        static::assertSame('附加数据', $order['attach']);
        static::assertSame(PaymentOrderStates::STATE_OPENED, $order['state']);
        static::assertNull($order['out_payment_no']);
        static::assertNotNull($order['expire_at']);
        static::assertNull($order['paid_at']);
        static::assertNull($order['closed_at']);
        // the pay parameters are only part of the create answer
        static::assertNull($order['pay']);
    }

    public function testGetUnknownOrder(): void
    {
        $client = $this->getBrowser();

        $path = self::PATH_ORDERS . '/PG20990101000000DEADBEEF';
        $client->request('GET', $path, $this->signedPayload('GET', $path));

        self::assertError($client->getResponse(), Response::HTTP_NOT_FOUND, PaymentException::ORDER_NOT_FOUND);
    }

    public function testGetOrderOfForeignApp(): void
    {
        $client = $this->getBrowser();

        $created = $this->createOrderViaApi($client);

        // a second, fully authorized app signs the request for the order of the first app
        $this->createContractApp();

        $path = self::PATH_ORDERS . '/' . $created['payment_no'];
        $client->request('GET', $path, $this->signedPayload('GET', $path));

        self::assertError($client->getResponse(), Response::HTTP_FORBIDDEN, PaymentException::FOREIGN_APP);
    }

    public function testCloseOrder(): void
    {
        $client = $this->getBrowser();

        $created = $this->createOrderViaApi($client);
        $path = self::PATH_ORDERS . '/' . $created['payment_no'] . '/close';

        $this->postJson($client, $path, $this->signedPayload('POST', $path));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $order = self::decodeResponse($response);
        static::assertSame(PaymentOrderStates::STATE_CLOSED, $order['state']);
        static::assertNotNull($order['closed_at']);
        static::assertNull($order['pay']);

        $getPath = self::PATH_ORDERS . '/' . $created['payment_no'];
        $client->request('GET', $getPath, $this->signedPayload('GET', $getPath));

        $order = self::decodeResponse($client->getResponse());
        static::assertSame(PaymentOrderStates::STATE_CLOSED, $order['state']);
        static::assertNotNull($order['closed_at']);
    }

    public function testCloseOrderTwiceIsConflict(): void
    {
        $client = $this->getBrowser();

        $created = $this->createOrderViaApi($client);
        $path = self::PATH_ORDERS . '/' . $created['payment_no'] . '/close';

        $this->postJson($client, $path, $this->signedPayload('POST', $path));
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $this->postJson($client, $path, $this->signedPayload('POST', $path));

        self::assertError($client->getResponse(), Response::HTTP_CONFLICT, PaymentException::ORDER_ALREADY_CLOSED);
    }

    public function testRecreateClosedOrderReportsItsState(): void
    {
        $client = $this->getBrowser();

        $business = ['out_trade_no' => 'OT' . bin2hex(random_bytes(8))] + self::CREATE_DEFAULTS;
        $created = $this->createOrderViaApi($client, $business);

        $closePath = self::PATH_ORDERS . '/' . $created['payment_no'] . '/close';
        $this->postJson($client, $closePath, $this->signedPayload('POST', $closePath));
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        // a retry after a lost answer reports the state of the order instead of an error
        $this->postJson($client, self::PATH_ORDERS, $this->signedPayload('POST', self::PATH_ORDERS, $business));
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $recreated = self::decodeResponse($response);
        static::assertSame($created['payment_no'], $recreated['payment_no']);
        static::assertSame(PaymentOrderStates::STATE_CLOSED, $recreated['state']);
        static::assertNull($recreated['pay']);
    }

    /**
     * Creates a payment order through the real create endpoint and answers its decoded body.
     *
     * @param array<string, mixed> $overrides business parameters of the create call, the defaults of a
     *                                        plain h5 order are merged in
     *
     * @return array<string, mixed>
     */
    private function createOrderViaApi(KernelBrowser $client, array $overrides = []): array
    {
        $business = $overrides + ['out_trade_no' => 'OT' . bin2hex(random_bytes(8))] + self::CREATE_DEFAULTS;

        $this->postJson($client, self::PATH_ORDERS, $this->signedPayload('POST', self::PATH_ORDERS, $business));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return self::decodeResponse($response);
    }
}
