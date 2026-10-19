<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;

/**
 * The payments the shop itself collects for a customer, e.g. a wallet top-up or a membership.
 *
 * It is deliberately the same path an external app takes - create the payment order, hand it to the
 * channel, answer the pay parameters - and only differs in the app: the shop pays through the platform
 * tenant, which the payment migration seeds and {@see PlatformPaymentApp} hands out. Nothing is
 * provisioned here, and nothing of the gateway is bypassed: the settlement of a paid payment reaches
 * this class as the `PaymentOrderPaidEvent` every other payment produces as well.
 *
 * @internal
 *
 * @final
 */
#[Package('checkout')]
class StorefrontPaymentService
{
    public function __construct(
        private readonly PlatformPaymentApp $platformApp,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentOrderCapture $capture,
    ) {
    }

    /**
     * The channels the storefront may offer: the ones the platform app can really collect through on
     * this device and in this currency. A channel whose merchant account the shop did not open is not
     * offered at all.
     *
     * @return list<array{channel: string, label: string, provider: string, provider_label: string, flow: string}>
     */
    public function channels(string $currency, ?string $device): array
    {
        return $this->platformApp->availableChannels($currency, $device);
    }

    /**
     * @param string $attach carried unchanged into the paid notification, used to find the intent again
     * @param int $amount the amount in the smallest unit of the currency
     *
     * @return array{payment_no: string, channel: string, params: array<string, mixed>}
     */
    public function open(
        string $outTradeNo,
        string $channel,
        int $amount,
        string $currency,
        string $subject,
        string $attach,
        ?string $returnUrl = null,
        ?string $clientIp = null,
        ?string $device = null,
    ): array {
        $app = $this->platformApp->appForChannel($channel);

        $order = $this->orderService->create($app, new PaymentOrderRequest(
            outTradeNo: $outTradeNo,
            channel: $channel,
            amount: $amount,
            currency: $currency,
            subject: $subject,
            attach: $attach,
            returnUrl: $returnUrl,
            // the same intent may be opened again when the payer switched the channel, the payment
            // order of the first attempt is then handed out again instead of a second one
            idempotencyKey: $outTradeNo,
            clientIp: $clientIp,
            device: $device,
        ));

        $result = $this->capture->capture($app, $order);

        return [
            'payment_no' => $order->paymentNo,
            'channel' => $channel,
            'params' => $result->params->jsonSerialize(),
        ];
    }
}
