<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The contract between the services of the payment gateway and the payum actions of the channels.
 *
 * The services put the payment data into an \ArrayObject - the model of the payum request - and the
 * actions of the channel read it. The outgoing request and the incoming answer are recorded on the
 * model as well, and finally the action stores its typed result in it:
 *
 *  - a Capture gets a {@see PayResult},
 *  - a Refund gets a {@see RefundResult},
 *  - a Notify gets a {@see NotificationResult}.
 *
 * A failing action records the request/answer as well and throws. That way a transaction keeps both
 * sides even when the channel rejected the call, which is exactly the case support needs most.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentGatewayModel
{
    /**
     * The typed result of the action.
     */
    final public const RESULT = 'result';

    /**
     * The payload sent to the channel.
     */
    final public const REQUEST = 'request';

    /**
     * The payload answered by the channel.
     */
    final public const RESPONSE = 'response';

    // ---- input: capture ----
    final public const PAYMENT_NO = 'payment_no';
    final public const OUT_TRADE_NO = 'out_trade_no';
    final public const AMOUNT = 'amount';
    final public const CURRENCY = 'currency';
    final public const SUBJECT = 'subject';
    final public const BODY = 'body';
    final public const ATTACH = 'attach';
    final public const CLIENT_IP = 'client_ip';
    final public const DEVICE = 'device';
    final public const EXPIRE_AT = 'expire_at';

    // ---- input: refund ----
    final public const OUT_PAYMENT_NO = 'out_payment_no';
    final public const ORDER_AMOUNT = 'order_amount';
    final public const REFUND_NO = 'refund_no';
    final public const OUT_REFUND_NO = 'out_refund_no';
    final public const REASON = 'reason';

    // ---- input: notification ----
    final public const RAW_BODY = 'raw_body';
    final public const HEADERS = 'headers';
    final public const REMOTE_IP = 'remote_ip';

    private function __construct()
    {
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    public static function forOrder(PaymentOrderEntity $order): \ArrayObject
    {
        return new \ArrayObject([
            self::PAYMENT_NO => $order->paymentNo,
            self::OUT_TRADE_NO => $order->outTradeNo,
            self::AMOUNT => $order->amount,
            self::CURRENCY => $order->currency,
            self::SUBJECT => $order->subject,
            self::BODY => $order->body,
            self::ATTACH => $order->attach,
            self::CLIENT_IP => $order->clientIp,
            self::DEVICE => $order->device,
            self::EXPIRE_AT => $order->expireAt,
        ]);
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    public static function forRefund(PaymentOrderEntity $order, PaymentRefundEntity $refund): \ArrayObject
    {
        return new \ArrayObject([
            self::PAYMENT_NO => $order->paymentNo,
            self::OUT_TRADE_NO => $order->outTradeNo,
            self::OUT_PAYMENT_NO => $order->outPaymentNo,
            self::AMOUNT => $refund->amount,
            // some channels want the total of the trade next to the refunded amount
            self::ORDER_AMOUNT => $order->amount,
            self::CURRENCY => $refund->currency,
            self::SUBJECT => $order->subject,
            self::REFUND_NO => $refund->refundNo,
            self::OUT_REFUND_NO => $refund->outRefundNo,
            self::REASON => $refund->reason,
        ]);
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return \ArrayObject<string, mixed>
     */
    public static function forNotification(string $rawBody, array $headers, ?string $remoteIp): \ArrayObject
    {
        return new \ArrayObject([
            self::RAW_BODY => $rawBody,
            self::HEADERS => $headers,
            self::REMOTE_IP => $remoteIp,
        ]);
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     * @param array<string, mixed> $payload
     */
    public static function recordRequest(\ArrayObject $model, array $payload): void
    {
        $model->offsetSet(self::REQUEST, $payload);
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     * @param array<string, mixed> $payload
     */
    public static function recordResponse(\ArrayObject $model, array $payload): void
    {
        $model->offsetSet(self::RESPONSE, $payload);
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     *
     * @return array<string, mixed>
     */
    public static function requestOf(\ArrayObject $model): array
    {
        $request = $model->offsetExists(self::REQUEST) ? $model->offsetGet(self::REQUEST) : null;

        return \is_array($request) ? $request : [];
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     *
     * @return array<string, mixed>
     */
    public static function responseOf(\ArrayObject $model): array
    {
        $response = $model->offsetExists(self::RESPONSE) ? $model->offsetGet(self::RESPONSE) : null;

        return \is_array($response) ? $response : [];
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    public static function payResult(\ArrayObject $model, string $channel): PayResult
    {
        $result = $model->offsetExists(self::RESULT) ? $model->offsetGet(self::RESULT) : null;

        if (!$result instanceof PayResult) {
            throw PaymentException::gatewayResultMissing($channel);
        }

        return $result;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    public static function refundResult(\ArrayObject $model, string $channel): RefundResult
    {
        $result = $model->offsetExists(self::RESULT) ? $model->offsetGet(self::RESULT) : null;

        if (!$result instanceof RefundResult) {
            throw PaymentException::gatewayResultMissing($channel);
        }

        return $result;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    public static function notificationResult(\ArrayObject $model, string $channel): NotificationResult
    {
        $result = $model->offsetExists(self::RESULT) ? $model->offsetGet(self::RESULT) : null;

        if (!$result instanceof NotificationResult) {
            throw PaymentException::gatewayResultMissing($channel);
        }

        return $result;
    }
}
