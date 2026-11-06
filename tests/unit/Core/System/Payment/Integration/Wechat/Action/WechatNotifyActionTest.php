<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Wechat\Action;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatNotifyAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Product\WechatProducts;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
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

    /**
     * @var array{0: string, 1: string}|null
     */
    private static ?array $platformKeys = null;

    public function testSupportsOnlyNotifyRequestsWithAPaymentGatewayModel(): void
    {
        $action = new WechatNotifyAction();

        static::assertTrue($action->supports(new Notify(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatNotifyAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(WechatNotifyAction::class));

        $action->execute(new Notify(new PaymentGatewayModel()));
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
            'out_trade_no' => 'PG-1',
            'trade_state' => WechatNotifyAction::TRADE_SUCCESS,
            'transaction_id' => '4200001234',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ];

        $model = $this->model($this->encryptedBody($resource), $this->signedHeaders($this->encryptedBody($resource)));

        $action = new WechatNotifyAction();
        $action->setApi($this->apiWithPlatformCertificate());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'wechat_h5');
        static::assertTrue($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('4200001234', $result->outPaymentNo);
        static::assertSame('PG-1', $result->paymentNo);
        static::assertFalse($result->isRefund);
        static::assertNull($result->outRefundId);
        static::assertSame(100, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame(WechatNotifyAction::TRADE_SUCCESS, $result->status);
        static::assertSame($resource, $result->raw);
    }

    /**
     * A body that claims SUCCESS without a valid signature is not decrypted at all: the api v3 key
     * would otherwise turn attacker controlled ciphertext into a readable answer, and there is no
     * amount, no status and no success in a notification that is not from wechat.
     */
    public function testAForgedNotificationIsNeitherDecryptedNorASuccess(): void
    {
        $model = $this->model($this->encryptedBody([
            'out_trade_no' => 'PG-1',
            'trade_state' => WechatNotifyAction::TRADE_SUCCESS,
            'transaction_id' => '4200001234',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ]), []);

        $action = new WechatNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'wechat_h5');

        static::assertFalse($result->signatureValid);
        static::assertFalse($result->succeeded);
        static::assertNull($result->amount);
        static::assertNull($result->outPaymentNo);
        static::assertNull($result->status);

        // the response is the message as it arrived - still ciphertext, never the decrypted resource
        static::assertArrayHasKey('ciphertext', $model->response()['resource']);
    }

    public function testExecuteMarksATradeThatDidNotSucceedAsNotSucceeded(): void
    {
        $resource = [
            'trade_state' => 'CLOSED',
            'transaction_id' => '4200001234',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ];

        $model = $this->model($this->encryptedBody($resource), $this->signedHeaders($this->encryptedBody($resource)));

        $action = new WechatNotifyAction();
        $action->setApi($this->apiWithPlatformCertificate());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'wechat_h5');
        static::assertFalse($result->succeeded);
        static::assertSame('CLOSED', $result->status);
    }

    public function testExecuteReadsARefundNotification(): void
    {
        $resource = [
            'out_trade_no' => 'PG-1',
            'out_refund_no' => 'PR-1',
            'refund_status' => WechatNotifyAction::REFUND_SUCCESS,
            'refund_id' => '5000000123',
            'amount' => ['refund' => 25, 'total' => 100, 'currency' => 'CNY'],
        ];

        $model = $this->model($this->encryptedBody($resource), $this->signedHeaders($this->encryptedBody($resource)));

        $action = new WechatNotifyAction();
        $action->setApi($this->apiWithPlatformCertificate());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'wechat_h5');
        static::assertTrue($result->succeeded);
        static::assertSame('5000000123', $result->outRefundId);
        static::assertSame('PG-1', $result->paymentNo);
        static::assertSame('PR-1', $result->refundNo);
        static::assertTrue($result->isRefund);
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

        static::assertTrue($model->result(NotificationResult::class, 'wechat_h5')->signatureValid);
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

        static::assertTrue($model->result(NotificationResult::class, 'wechat_h5')->signatureValid);
    }

    public function testExecuteDoesNotVerifyWhenTheSignatureHeadersAreMissing(): void
    {
        $body = $this->encryptedBody(['trade_state' => WechatNotifyAction::TRADE_SUCCESS, 'amount' => ['total' => 100, 'currency' => 'CNY']]);

        $model = $this->model($body, [WechatNotifyAction::HEADER_TIMESTAMP => '1700000000']);

        $action = new WechatNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        static::assertFalse($model->result(NotificationResult::class, 'wechat_h5')->signatureValid);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function expectNotificationInvalid(array $values): void
    {
        $action = new WechatNotifyAction();
        $action->setApi($this->apiWithPlatformCertificate());

        // signed on purpose: a malformed resource has to be reached past the signature check, an
        // unsigned body is refused before it is ever decrypted and would test nothing of the sort
        $values['headers'] = $this->signedHeaders((string) $values['raw_body']);

        try {
            $action->execute(new Notify(new PaymentGatewayModel($values)));
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
     */
    private function model(string $rawBody, array $headers): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::RAW_BODY => $rawBody,
            PaymentGatewayModel::HEADERS => $headers,
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): WechatApi
    {
        $channel = new PaymentProduct(WechatProducts::H5, 'wechat', WechatProducts::H5, [PaymentInteraction::REDIRECT], 'wechat');

        return new WechatApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'wechat',
                    'wechat',
                    [],
                    [WechatProducts::H5 => $channel],
                    new PaymentGatewayFactory('wechat', WechatApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: array_replace([WechatApi::API_V3_KEY => self::API_V3_KEY], $credentials),
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            '',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function platformKeys(): array
    {
        return self::$platformKeys ??= $this->keyPair();
    }

    private function apiWithPlatformCertificate(): WechatApi
    {
        return $this->api([WechatApi::PLATFORM_CERT => $this->platformKeys()[1]]);
    }

    /**
     * Headers whose signature really verifies against the platform certificate of the api, so the
     * notification is decrypted and read instead of being refused before it is looked at.
     *
     * @return array<string, string>
     */
    private function signedHeaders(string $rawBody): array
    {
        $timestamp = '1700000000';
        $nonce = 'notification-nonce';

        openssl_sign(
            $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n",
            $signature,
            $this->platformKeys()[0],
            \OPENSSL_ALGO_SHA256
        );

        return [
            WechatNotifyAction::HEADER_TIMESTAMP => $timestamp,
            WechatNotifyAction::HEADER_NONCE => $nonce,
            WechatNotifyAction::HEADER_SIGNATURE => base64_encode($signature),
        ];
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
