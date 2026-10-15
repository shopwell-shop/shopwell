<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Model\ArrayObject;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The model of a payum request: the contract between the services of the payment gateway and the
 * payum actions of the channels.
 *
 * It extends the array object payum itself ships as its model, so the actions address it the payum
 * way while the typed accessors keep the keys of this platform in one place. The services put the
 * payment data into the model and the actions of the channel read it; the outgoing request and the
 * incoming answer are recorded on the model as well, and finally the action stores its typed result
 * in it:
 *
 *  - a Capture gets a {@see PayResult},
 *  - a Refund gets a {@see RefundResult},
 *  - a Notify gets a {@see NotificationResult},
 *  - a Query gets a {@see QueryResult}.
 *
 * A failing action records the request/answer as well and throws. That way a transaction keeps both
 * sides even when the channel rejected the call, which is exactly the case support needs most.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentGatewayModel extends ArrayObject
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

    // ---- input: query ----
    final public const LAST_CHANNEL_RESPONSE = 'last_channel_response';

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->details = $values;
    }

    public static function forOrder(PaymentOrderEntity $order): self
    {
        return new self([
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

    public static function forRefund(PaymentOrderEntity $order, PaymentRefundEntity $refund): self
    {
        return new self([
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
     */
    public static function forNotification(string $rawBody, array $headers, ?string $remoteIp): self
    {
        return new self([
            self::RAW_BODY => $rawBody,
            self::HEADERS => $headers,
            self::REMOTE_IP => $remoteIp,
        ]);
    }

    /**
     * The model of a query: the order data - the interfaces that can be queried by the merchant order
     * number read it from there - next to the answer of the last capture, which is where the
     * interfaces that hand out their own ids for a payment are found again, e.g. the order id of
     * paypal or the checkout session of stripe.
     *
     * @param array<string, mixed> $lastChannelResponse
     */
    public static function forQuery(PaymentOrderEntity $order, array $lastChannelResponse = []): self
    {
        $model = self::forOrder($order);
        $model->offsetSet(self::LAST_CHANNEL_RESPONSE, $lastChannelResponse);

        return $model;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordRequest(array $payload): void
    {
        $this->offsetSet(self::REQUEST, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordResponse(array $payload): void
    {
        $this->offsetSet(self::RESPONSE, $payload);
    }

    /**
     * The payload the action sent to the channel, or an empty array when it threw before it could.
     *
     * @return array<string, mixed>
     */
    public function request(): array
    {
        $request = $this->offsetExists(self::REQUEST) ? $this->offsetGet(self::REQUEST) : null;

        return \is_array($request) ? $request : [];
    }

    /**
     * The payload the channel answered, or an empty array when it never did.
     *
     * @return array<string, mixed>
     */
    public function response(): array
    {
        $response = $this->offsetExists(self::RESPONSE) ? $this->offsetGet(self::RESPONSE) : null;

        return \is_array($response) ? $response : [];
    }

    /**
     * The typed result the action stored, e.g. a {@see PayResult} after a capture - the three result
     * types share one read because they share one storage: whatever the action was, a result that is
     * missing or of the wrong kind means the action did not finish its work.
     *
     * @template TResult of object
     *
     * @param class-string<TResult> $resultClass
     *
     * @return TResult
     */
    public function result(string $resultClass, string $channel): object
    {
        $result = $this->offsetExists(self::RESULT) ? $this->offsetGet(self::RESULT) : null;

        if (!$result instanceof $resultClass) {
            throw PaymentException::gatewayResultMissing($channel);
        }

        return $result;
    }
}
