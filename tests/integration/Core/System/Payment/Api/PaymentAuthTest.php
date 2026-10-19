<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of the app authentication of the payment api: every endpoint but notify and return
 * authenticates the caller through app_id, timestamp, nonce and sign.
 *
 * @internal
 */
#[Package('framework')]
class PaymentAuthTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    private const PATH_ORDERS = '/payment-api/v1/payment-orders';
    private const PATH_CHANNELS = '/payment-api/v1/channels';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAlipayPlatform();
        $this->createContractApp();
    }

    public function testMissingAuthParameters(): void
    {
        $client = $this->getBrowser();

        $this->postJson($client, self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        self::assertError($client->getResponse(), Response::HTTP_UNAUTHORIZED, PaymentException::MISSING_AUTH_PARAMETERS);
    }

    public function testUnknownApp(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ], appId: 'unknown_app', secret: 'irrelevant');

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_UNAUTHORIZED, PaymentException::APP_NOT_FOUND);
    }

    public function testDisabledApp(): void
    {
        $this->createContractApp(active: false, withProvider: false, withChannel: false);

        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ]);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_FORBIDDEN, PaymentException::APP_DISABLED);
    }

    public function testExpiredTimestamp(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ], timestamp: time() - 301);

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_UNAUTHORIZED, PaymentException::EXPIRED_TIMESTAMP);
    }

    public function testInvalidSignature(): void
    {
        $client = $this->getBrowser();

        $payload = $this->signedPayload('POST', self::PATH_ORDERS, [
            'out_trade_no' => 'OT' . bin2hex(random_bytes(8)),
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '测试商品',
        ], secret: 'not-the-app-secret');

        $this->postJson($client, self::PATH_ORDERS, $payload);

        self::assertError($client->getResponse(), Response::HTTP_UNAUTHORIZED, PaymentException::INVALID_SIGNATURE);
    }

    public function testReusedNonceIsRejected(): void
    {
        $client = $this->getBrowser();

        $query = $this->signedPayload('GET', self::PATH_CHANNELS, [], nonce: bin2hex(random_bytes(16)));

        $client->request('GET', self::PATH_CHANNELS, $query);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request('GET', self::PATH_CHANNELS, $query);
        self::assertError($client->getResponse(), Response::HTTP_UNAUTHORIZED, PaymentException::INVALID_SIGNATURE);
    }
}
