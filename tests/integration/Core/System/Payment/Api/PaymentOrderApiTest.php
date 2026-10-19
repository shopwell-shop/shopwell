<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of `POST /payment-api/v1/payment-orders`, `GET /payment-api/v1/payment-orders/{paymentNo}`
 * and `POST /payment-api/v1/payment-orders/{paymentNo}/close`.
 *
 * The tests are about the HTTP contract, not about the calls to the interface: closing a payment is
 * the only call the platform initiates itself, and its success path is covered by
 * {@see \Shopwell\Tests\Unit\Core\System\Payment\Order\PaymentOrderCloserTest},
 * {@see \Shopwell\Tests\Unit\Core\System\Payment\Gateway\PaymentCloserTest} and
 * {@see \Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay\AlipayCloseActionTest}. The
 * integration layer therefore only exercises the paths that are decided before the interface is
 * called - the closed order is seeded directly in those states.
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
        // the api answers the storage format of the platform, not iso 8601
        static::assertNotFalse(\DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, (string) $order['expire_at']));
        static::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/', (string) $order['expire_at']);
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

    public function testCloseUnknownOrder(): void
    {
        $client = $this->getBrowser();

        $path = self::PATH_ORDERS . '/PG20990101000000DEADBEEF/close';
        $this->postJson($client, $path, $this->signedPayload('POST', $path));

        // the order is resolved before the interface is involved, so nothing leaves the process
        self::assertError($client->getResponse(), Response::HTTP_NOT_FOUND, PaymentException::ORDER_NOT_FOUND);
    }

    public function testCloseClosedOrderIsConflict(): void
    {
        $client = $this->getBrowser();

        $seed = $this->seedClosedOrder();
        $path = self::PATH_ORDERS . '/' . $seed['paymentNo'] . '/close';

        $this->postJson($client, $path, $this->signedPayload('POST', $path));

        // a closed order is refused before the interface is called, see the class doc
        self::assertError($client->getResponse(), Response::HTTP_CONFLICT, PaymentException::ORDER_ALREADY_CLOSED);
    }

    public function testRecreateClosedOrderReportsItsState(): void
    {
        $client = $this->getBrowser();

        $seed = $this->seedClosedOrder();

        // a retry after the order was closed reports its state instead of an error, and the closed
        // order is answered as it is - the interface is never called for it again
        $business = ['out_trade_no' => $seed['outTradeNo']] + self::CREATE_DEFAULTS;
        $this->postJson($client, self::PATH_ORDERS, $this->signedPayload('POST', self::PATH_ORDERS, $business));
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $recreated = self::decodeResponse($response);
        static::assertSame($seed['paymentNo'], $recreated['payment_no']);
        static::assertSame(PaymentOrderStates::STATE_CLOSED, $recreated['state']);
        static::assertNull($recreated['pay']);
    }

    /**
     * A closed order is seeded in its final state instead of being closed through the api: the close
     * endpoint is the one call that leaves the process, and the fixtures signed with a locally
     * generated key pair can never succeed against the real interface. The state itself is what the
     * contract tests are about.
     *
     * @return array{id: string, paymentNo: string, outTradeNo: string}
     */
    private function seedClosedOrder(): array
    {
        $seed = $this->seedPaymentOrder();

        /** @var EntityRepository<PaymentOrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('payment_order.repository');
        $orderRepository->update([[
            'id' => $seed['id'],
            'stateId' => $this->paymentOrderStateId(PaymentOrderStates::STATE_CLOSED),
            'closedAt' => new \DateTimeImmutable(),
        ]], Context::createDefaultContext());

        return $seed;
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
