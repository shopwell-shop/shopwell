<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;

/**
 * Locks the paths the wechat api sends its business numbers on.
 *
 * The sdk rewrites a chained segment from camel case to kebab case, so a payment number of ours
 * (`PG` plus upper case hex) would be sent character by character mangled - a 404 that the
 * reconciliation reads as "the trade does not exist". These tests assert the path byte for byte and
 * that the request is still signed, so nobody can move the calls back to `chain()` unnoticed.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatApi::class)]
class WechatApiRequestPathTest extends TestCase
{
    private const PAYMENT_NO = 'PG20261018AB12CD34';

    private const PLATFORM_SERIAL = 'PLATFORM-SERIAL';

    /**
     * @var array{merchant: array{0: string, 1: string}, platform: array{0: string, 1: string}}|null
     */
    private static ?array $keys = null;

    private FakeWechatHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new FakeWechatHandler($this->platformPrivateKey(), self::PLATFORM_SERIAL);
    }

    public function testTheOrderQuerySendsThePaymentNumberVerbatim(): void
    {
        $this->handler->answer(200, [
            'trade_state' => 'NOTPAY',
            'transaction_id' => 'TRANSACTION-1',
            'amount' => ['total' => 1000, 'currency' => 'CNY'],
        ]);

        $result = $this->api()->query(self::PAYMENT_NO);

        $call = $this->handler->only();

        static::assertSame('GET', $call['method']);
        // byte for byte - a lower cased or dash separated number asks for a trade nobody opened
        static::assertSame('/v3/pay/transactions/out-trade-no/' . self::PAYMENT_NO, $call['path']);
        static::assertStringContainsString('mchid=1900000001', $call['url']);

        static::assertSame(QueryResult::STATUS_PENDING, $result->status);
        static::assertSame('TRANSACTION-1', $result->outPaymentNo);
    }

    public function testTheCloseSendsThePaymentNumberVerbatim(): void
    {
        $this->handler->answer(204, []);

        $result = $this->api()->close(self::PAYMENT_NO);

        $call = $this->handler->only();

        static::assertSame('POST', $call['method']);
        static::assertSame('/v3/pay/transactions/out-trade-no/' . self::PAYMENT_NO . '/close', $call['path']);
        static::assertSame(['mchid' => '1900000001'], $call['body']);

        static::assertSame(CloseResult::STATUS_CLOSED, $result->status);
        static::assertSame('204', $result->channelStatus);
    }

    /**
     * A request that bypasses `chain()` must not bypass the signer: the signer is a middleware of the
     * client behind the driver, and a payout or a close wechat cannot attribute to the merchant is
     * refused.
     */
    public function testTheQueryIsSignedWithTheMerchantCertificate(): void
    {
        $this->handler->answer(200, ['trade_state' => 'NOTPAY', 'amount' => ['total' => 1000]]);

        $this->api()->query(self::PAYMENT_NO);

        $authorization = $this->handler->only()['headers']['Authorization'] ?? '';

        static::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048 ', $authorization);
        static::assertStringContainsString('mchid="1900000001"', $authorization);
        static::assertStringContainsString('serial_no="MCH-SERIAL"', $authorization);
    }

    public function testTheCloseIsSignedWithTheMerchantCertificate(): void
    {
        $this->handler->answer(204, []);

        $this->api()->close(self::PAYMENT_NO);

        $authorization = $this->handler->only()['headers']['Authorization'] ?? '';

        static::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048 ', $authorization);
    }

    /**
     * The 404 semantics do not change with the way the path is built.
     */
    public function testAnUnknownTradeIsStillAbsent(): void
    {
        $this->handler->answer(404, ['code' => 'ORDER_NOT_EXIST', 'message' => 'the trade does not exist']);

        $result = $this->api()->query(self::PAYMENT_NO);

        static::assertSame(QueryResult::STATUS_ABSENT, $result->status);
    }

    public function testClosingAnUnknownTradeIsStillNotFound(): void
    {
        $this->handler->answer(404, ['code' => 'ORDER_NOT_EXIST', 'message' => 'the trade does not exist']);

        $result = $this->api()->close(self::PAYMENT_NO);

        static::assertSame(CloseResult::STATUS_NOT_FOUND, $result->status);
        static::assertSame('ORDER_NOT_EXIST', $result->channelStatus);
    }

    private function platformPrivateKey(): string
    {
        return self::keys()['platform'][0];
    }

    private function platformPublicKey(): string
    {
        return self::keys()['platform'][1];
    }

    private function merchantPrivateKey(): string
    {
        return self::keys()['merchant'][0];
    }

    /**
     * @return array{merchant: array{0: string, 1: string}, platform: array{0: string, 1: string}}
     */
    private static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        self::$keys = ['merchant' => self::keyPair(), 'platform' => self::keyPair()];

        return self::$keys;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return [(string) $privateKey, (string) $details['key']];
    }

    private function api(): WechatApi
    {
        $channel = new PaymentChannel(WechatApi::CHANNEL_H5, 'wechat', WechatApi::CHANNEL_H5, PaymentFlow::REDIRECT);

        return new WechatApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'wechat',
                    'wechat',
                    [],
                    [WechatApi::CHANNEL_H5 => $channel],
                    new PaymentGatewayFactory('wechat', WechatApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: [
                    WechatApi::MCH_ID => '1900000001',
                    WechatApi::MCH_SERIAL => 'MCH-SERIAL',
                    WechatApi::MCH_PRIVATE_KEY => $this->merchantPrivateKey(),
                    WechatApi::PLATFORM_CERT => $this->platformPublicKey(),
                    WechatApi::PLATFORM_SERIAL => self::PLATFORM_SERIAL,
                ],
                params: [WechatApi::PARAM_APP_ID => 'wx-app-id'],
                runtimeParams: [],
                settings: [],
                mode: 'live',
            ),
            '',
            '',
            $this->handler->stack(),
        );
    }
}
