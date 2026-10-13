<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotifyAction;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatNotifyAction::class)]
class WechatNotifyActionTest extends TestCase
{
    private const API_V3_KEY = 'a-256-bit-api-v3-key-value-12345';

    public function testSupportsOnlyNotifyRequestsWithAnArrayObjectModel(): void
    {
        $action = new WechatNotifyAction();

        static::assertTrue($action->supports(new Notify(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatNotifyAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(WechatNotifyAction::class));

        $action->execute(new Notify(new \ArrayObject()));
    }

    public function testExecuteThrowsWhenTheNotificationIsNotJson(): void
    {
        $this->expectNotificationInvalid(['raw_body' => 'not-json', 'headers' => []]);
    }

    public function testExecuteThrowsWhenTheNotificationHasNoResource(): void
    {
        $this->expectNotificationInvalid(['raw_body' => '{"id":"evt-1"}', 'headers' => []]);
    }

    public function testExecuteThrowsWhenTheResourceCarriesNoCiphertext(): void
    {
        $this->expectNotificationInvalid(['raw_body' => '{"resource":{"id":"evt-1"}}', 'headers' => []]);
    }

    public function testExecuteThrowsWhenTheResourceCannotBeDecrypted(): void
    {
        $this->expectNotificationInvalid([
            'raw_body' => json_encode(['resource' => ['ciphertext' => 'not-a-ciphertext', 'nonce' => 'n', 'associated_data' => 'a']], \JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);
    }

    public function testExecuteReadsASuccessfulTradeNotification(): void
    {
        $resource = [
            'trade_state' => WechatNotifyAction::TRADE_SUCCESS,
            'transaction_id' => '4200001234',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ];

        $model = $this->model($this->encryptedBody($resource), []);

        $action = new WechatNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'wechat_h5');
        static::assertFalse($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('4200001234', $result->outPaymentNo);
        static::assertNull($result->outRefundId);
        static::assertSame(100, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame(WechatNotifyAction::TRADE_SUCCESS, $result->status);
        static::assertSame($resource, $result->raw);
    }

    public function testExecuteMarksATradeThatDidNotSucceedAsNotSucceeded(): void
    {
        $resource = [
            'trade_state' => 'CLOSED',
            'transaction_id' => '4200001234',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ];

        $model = $this->model($this->encryptedBody($resource), []);

        $action = new WechatNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'wechat_h5');
        static::assertFalse($result->succeeded);
        static::assertSame('CLOSED', $result->status);
    }

    public function testExecuteReadsARefundNotification(): void
    {
        $resource = [
            'refund_status' => WechatNotifyAction::REFUND_SUCCESS,
            'refund_id' => '5000000123',
            'amount' => ['refund' => 25, 'total' => 100, 'currency' => 'CNY'],
        ];

        $model = $this->model($this->encryptedBody($resource), []);

        $action = new WechatNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'wechat_h5');
        static::assertTrue($result->succeeded);
        static::assertSame('5000000123', $result->outRefundId);
        static::assertNull($result->outPaymentNo);
        static::assertSame(25, $result->amount);
        static::assertSame(WechatNotifyAction::REFUND_SUCCESS, $result->status);
    }

    public function testExecuteVerifiesASignatureOfThePlatformCertificate(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        $body = $this->encryptedBody(['trade_state' => WechatNotifyAction::TRADE_SUCCESS, 'amount' => ['total' => 100, 'currency' => 'CNY']]);
        $timestamp = '1700000000';
        $nonce = 'header-nonce';
        $signature = Rsa::sign(
            $timestamp . "\n" . $nonce . "\n" . $body . "\n",
            Rsa::from($privateKey, Rsa::KEY_TYPE_PRIVATE)
        );

        $model = $this->model($body, [
            WechatNotifyAction::HEADER_TIMESTAMP => $timestamp,
            WechatNotifyAction::HEADER_NONCE => $nonce,
            WechatNotifyAction::HEADER_SIGNATURE => $signature,
            WechatNotifyAction::HEADER_SERIAL => 'PLATFORM-SERIAL',
        ]);

        $action = new WechatNotifyAction();
        $action->setApi($this->api([
            WechatApi::PLATFORM_CERT => $publicKey,
            WechatApi::PLATFORM_SERIAL => 'PLATFORM-SERIAL',
        ]));
        $action->execute(new Notify($model));

        static::assertTrue(PaymentGatewayModel::notificationResult($model, 'wechat_h5')->signatureValid);
    }

    public function testExecuteNormalizesTheHeaderNamesAndValues(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        $body = $this->encryptedBody(['trade_state' => WechatNotifyAction::TRADE_SUCCESS, 'amount' => ['total' => 100, 'currency' => 'CNY']]);
        $timestamp = '1700000000';
        $nonce = 'header-nonce';
        $signature = Rsa::sign(
            $timestamp . "\n" . $nonce . "\n" . $body . "\n",
            Rsa::from($privateKey, Rsa::KEY_TYPE_PRIVATE)
        );

        // the header names arrive in the http casing and as a list of values
        $model = $this->model($body, [
            'Wechatpay-Timestamp' => [$timestamp],
            'Wechatpay-Nonce' => [$nonce],
            'Wechatpay-Signature' => [$signature],
            'Wechatpay-Serial' => ['PLATFORM-SERIAL'],
        ]);

        $action = new WechatNotifyAction();
        $action->setApi($this->api([
            WechatApi::PLATFORM_CERT => $publicKey,
            WechatApi::PLATFORM_SERIAL => 'PLATFORM-SERIAL',
        ]));
        $action->execute(new Notify($model));

        static::assertTrue(PaymentGatewayModel::notificationResult($model, 'wechat_h5')->signatureValid);
    }

    public function testExecuteDoesNotVerifyWhenTheSignatureHeadersAreMissing(): void
    {
        $body = $this->encryptedBody(['trade_state' => WechatNotifyAction::TRADE_SUCCESS, 'amount' => ['total' => 100, 'currency' => 'CNY']]);

        $model = $this->model($body, [WechatNotifyAction::HEADER_TIMESTAMP => '1700000000']);

        $action = new WechatNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        static::assertFalse(PaymentGatewayModel::notificationResult($model, 'wechat_h5')->signatureValid);
    }

    /**
     * @param array<string, mixed> $model
     */
    private function expectNotificationInvalid(array $model): void
    {
        $action = new WechatNotifyAction();
        $action->setApi($this->api());

        try {
            $action->execute(new Notify(new \ArrayObject($model)));
            static::fail('The notification was expected to be rejected.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_INVALID, $exception->getErrorCode());
        }
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function encryptedBody(array $resource): string
    {
        $nonce = 'notification';
        $associatedData = 'transaction';
        $ciphertext = AesGcm::encrypt(json_encode($resource, \JSON_THROW_ON_ERROR), self::API_V3_KEY, $nonce, $associatedData);

        return json_encode([
            'resource' => [
                'ciphertext' => $ciphertext,
                'nonce' => $nonce,
                'associated_data' => $associatedData,
            ],
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return \ArrayObject<string, mixed>
     */
    private function model(string $rawBody, array $headers): \ArrayObject
    {
        return new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $rawBody,
            PaymentGatewayModel::HEADERS => $headers,
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): WechatApi
    {
        return new WechatApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'wechat',
            AbstractPaymentApi::CONFIG_CHANNEL => 'wechat_h5',
            AbstractPaymentApi::CONFIG_MODE => 'live',
            AbstractPaymentApi::CONFIG_CREDENTIALS => array_replace([WechatApi::API_V3_KEY => self::API_V3_KEY], $credentials),
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ]);
    }

    /**
     * @return array{string, string}
     */
    private function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return [(string) $privateKey, (string) $details['key']];
    }
}
