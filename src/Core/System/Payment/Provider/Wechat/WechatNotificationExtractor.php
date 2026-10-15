<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;

/**
 * There is nothing to extract from a wechat pay notification: wechat encrypts its payload with the api
 * v3 key of the merchant, so the payment number cannot be read before the app is known.
 *
 * The platform therefore hands wechat a notify url per payment
 * (`{base}/payment-api/v1/notify/wechat_h5/{paymentNo}`) and the router passes the payment number in:
 * that is what tells us which app the notification belongs to, and only then can its key decrypt it.
 *
 * A wechat merchant account configured with one global notification url cannot be served this way.
 *
 * @internal
 */
#[Package('framework')]
final class WechatNotificationExtractor implements PaymentNotificationExtractorInterface
{
    public function extract(string $rawBody, array $headers): PaymentNotificationData
    {
        return new PaymentNotificationData();
    }

    /**
     * Wechat pay repeats a notification unless it is acknowledged with http 200 and this body.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck(
            '{"code":"SUCCESS","message":"成功"}',
            'application/json; charset=utf-8'
        );
    }
}
