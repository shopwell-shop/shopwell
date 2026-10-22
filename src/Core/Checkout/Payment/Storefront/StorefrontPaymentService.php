<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront;

use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
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
        private readonly AbstractTranslator $translator,
    ) {
    }

    /**
     * The channels the storefront may offer: the ones the platform app can really collect through on
     * this device and in this currency. A channel whose merchant account the shop did not open is not
     * offered at all.
     *
     * The label a provider declares belongs to the administration, where an operator reads it while
     * configuring the channel. A payer reads the channel at the checkout and in the wallet, so the
     * name is translated here - see {@see self::channelLabel()}.
     *
     * @return list<array{channel: string, label: string, provider: string, provider_label: string, flow: string}>
     */
    public function channels(string $currency, ?string $device): array
    {
        $channels = [];

        foreach ($this->platformApp->availableChannels($currency, $device) as $channel) {
            $channel['label'] = $this->channelLabel($channel['channel'], $channel['label']);

            $channels[] = $channel;
        }

        return $channels;
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

    /**
     * The storefront name of a channel, e.g. `payment.channel.wechat_native`.
     *
     * A provider names its channels for the administration, where the declared label is a snippet key
     * of that interface. Printing it in the storefront would show a payer a key or an untranslated
     * string, so the storefront translates the channel itself. A channel that has no storefront
     * snippet - typically one a plugin added - is shown as its technical name, and a provider that
     * declared a readable label instead of a key keeps it.
     */
    private function channelLabel(string $technicalName, string $declaredLabel): string
    {
        $key = 'payment.channel.' . $technicalName;
        $label = $this->translator->trans($key);

        if ($label !== $key) {
            return $label;
        }

        if (!str_contains($declaredLabel, '.')) {
            return $declaredLabel;
        }

        return ucwords(str_replace('_', ' ', $technicalName));
    }
}
