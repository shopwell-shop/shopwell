<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * The references an incoming channel notification carries, extracted **without** credentials.
 *
 * The values are not trusted: they are only used to locate the payment order (or the refund), so that
 * the credentials of that very app can be resolved and the notification can then be verified by the
 * payum action. Nothing may be written before the verification succeeded.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentNotificationData
{
    public function __construct(
        /**
         * The payment number of the platform. The channels always send it back as their merchant
         * order number, because that is what the platform hands to them when the order is created.
         */
        public ?string $paymentNo = null,
        public ?string $outTradeNo = null,
        public ?string $outPaymentNo = null,
        /**
         * The refund number of the platform, sent back as the merchant refund number.
         */
        public ?string $refundNo = null,
        /**
         * The transfer number of the platform, sent back as the merchant payout number. It is the
         * payout counterpart of `paymentNo`: a notification names one of the two, never both.
         *
         * Declared last on purpose - the notification processor builds this object positionally.
         */
        public ?string $transferNo = null,
    ) {
    }
}
