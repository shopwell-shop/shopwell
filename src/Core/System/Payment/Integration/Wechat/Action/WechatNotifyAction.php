<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\Request\Payload;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatNotificationCodec;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

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

    public function capability(): PaymentCapability
    {
        return PaymentCapability::NOTIFICATION;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $rawBody = $this->stringValue($model, PaymentGatewayModel::RAW_BODY);
        $headers = $this->headersOf($model);

        $signatureValid = WechatNotificationCodec::verify($api->credentials(), $rawBody, $headers);

        $message = $this->decode($rawBody);

        if (!$signatureValid) {
            // a forged body is never decrypted: the api v3 key would turn attacker controlled
            // ciphertext into a readable answer, and a notification that is not from wechat has no
            // amount, no status and no success to report - only the evidence of the attempt
            $this->recordResponse($model, $message);
            $this->setResult($model, new NotificationResult(signatureValid: false, succeeded: false));

            return;
        }

        $resource = WechatNotificationCodec::decryptResource($api->credentials(), $message);
        $outRefundId = Payload::string($resource, 'refund_id');

        $this->recordResponse($model, $resource);

        $tradeState = Payload::string($resource, 'trade_state');
        $refundStatus = Payload::string($resource, 'refund_status');

        // only reached with a valid signature, so a reported success is a success of the channel
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
