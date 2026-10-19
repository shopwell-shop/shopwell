<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\Payload;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * Verifies and decrypts the asynchronous notification of wechat pay api v3.
 *
 * Wechat signs the notification with the private key of the platform and encrypts its payload with the
 * api v3 key of the merchant: the signature is verified with the platform certificate, the payload is
 * then decrypted with the key of the app the payment belongs to.
 *
 * @internal
 */
#[Package('framework')]
final class WechatNotifyAction extends AbstractPaymentAction
{
    final public const HEADER_TIMESTAMP = 'wechatpay-timestamp';
    final public const HEADER_NONCE = 'wechatpay-nonce';
    final public const HEADER_SIGNATURE = 'wechatpay-signature';
    final public const HEADER_SERIAL = 'wechatpay-serial';

    final public const TRADE_SUCCESS = 'SUCCESS';
    final public const REFUND_SUCCESS = 'SUCCESS';

    protected string $apiClass = WechatApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $rawBody = $this->stringValue($model, PaymentGatewayModel::RAW_BODY);
        $headers = $this->headersOf($model);

        $signatureValid = $this->verify($api, $rawBody, $headers);

        $message = $this->decode($rawBody);
        $resource = $this->decryptResource($api, $message);
        $outRefundId = Payload::string($resource, 'refund_id');

        $this->recordResponse($model, $resource);

        $tradeState = Payload::string($resource, 'trade_state');
        $refundStatus = Payload::string($resource, 'refund_status');

        $this->setResult($model, new NotificationResult(
            signatureValid: $signatureValid,
            succeeded: $outRefundId === null
                ? $tradeState === self::TRADE_SUCCESS
                : $refundStatus === self::REFUND_SUCCESS,
            outPaymentNo: Payload::string($resource, 'transaction_id'),
            outRefundId: $outRefundId,
            amount: $this->amountOf($resource, $outRefundId !== null),
            currency: strtoupper($this->currencyOf($resource)),
            status: $tradeState ?? $refundStatus,
            raw: $resource,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Notify::class);
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }

    /**
     * The signature covers the timestamp, the nonce and the raw body, see the wechat pay api v3 docs.
     *
     * @param array<string, string> $headers
     */
    private function verify(WechatApi $api, string $rawBody, array $headers): bool
    {
        $timestamp = $headers[self::HEADER_TIMESTAMP] ?? '';
        $nonce = $headers[self::HEADER_NONCE] ?? '';
        $signature = $headers[self::HEADER_SIGNATURE] ?? '';

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return false;
        }

        $certificate = $api->platformCertOf($headers[self::HEADER_SERIAL] ?? null);

        if ($certificate === null) {
            return false;
        }

        try {
            return Rsa::verify(
                $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n",
                $signature,
                $api->publicKeyOf($certificate)
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    private function decode(string $rawBody): array
    {
        $message = json_decode($rawBody, true);

        if (!\is_array($message)) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the notification is not a json object');
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    private function decryptResource(WechatApi $api, array $message): array
    {
        $resource = $message['resource'] ?? null;

        if (!\is_array($resource) || !\is_string($resource['ciphertext'] ?? null)) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the notification carries no encrypted resource');
        }

        try {
            $plain = AesGcm::decrypt(
                $resource['ciphertext'],
                $api->apiV3Key(),
                \is_string($resource['nonce'] ?? null) ? $resource['nonce'] : '',
                \is_string($resource['associated_data'] ?? null) ? $resource['associated_data'] : ''
            );
        } catch (\Throwable $exception) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, $exception->getMessage());
        }

        $decoded = json_decode($plain, true);

        if (!\is_array($decoded)) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the decrypted resource is not a json object');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function amountOf(array $resource, bool $isRefund): ?int
    {
        $amount = Payload::map($resource, 'amount');

        if ($amount === []) {
            return null;
        }

        $value = $isRefund ? ($amount['refund'] ?? null) : ($amount['total'] ?? null);

        return \is_int($value) ? $value : (\is_numeric($value) ? (int) $value : null);
    }

    /**
     * The currency of the notification, in the upper case form the component records it.
     *
     * @param array<string, mixed> $resource
     */
    private function currencyOf(array $resource): string
    {
        return Payload::string(Payload::map($resource, 'amount'), 'currency', 'CNY') ?? 'CNY';
    }
}
