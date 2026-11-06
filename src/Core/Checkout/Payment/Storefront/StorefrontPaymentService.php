<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront;

use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierService;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Routing\PaymentContext;

/**
 * Wallet and membership purchases use the same cashier sessions as external apps.
 * Browser consumers choose a method; the common route chooses product and merchant account.
 *
 * @internal
 */
#[Package('checkout')]
class StorefrontPaymentService
{
    public const PROFILE = 'h5';

    public function __construct(
        private readonly PlatformPaymentApp $platformApp,
        private readonly PaymentCashierService $cashier,
        private readonly AbstractTranslator $translator,
    ) {
    }

    /**
     * @return list<array{method: string, label: string, flow: string}>
     */
    public function options(PaymentContext $context): array
    {
        return array_map(fn (array $option): array => [
            'method' => $option['method'],
            'label' => $this->translator->trans($option['translation_key']),
            'flow' => $option['interaction'],
        ], $this->cashier->options($this->platformApp->app(), self::PROFILE, $context));
    }

    public function resolveMethod(string $method, PaymentContext $context): string
    {
        foreach ($this->options($context) as $option) {
            if ($option['method'] === $method) {
                return $method;
            }
        }

        throw PaymentException::channelNotAvailableInStorefront($method);
    }

    /**
     * @return array{payment_no: string, channel: string, params: array<string, mixed>}
     */
    public function open(
        string $outTradeNo,
        string $method,
        int $amount,
        string $subject,
        string $attach,
        PaymentContext $paymentContext,
        ?string $returnUrl = null,
        ?string $clientIp = null,
    ): array {
        $app = $this->platformApp->app();
        $session = $this->cashier->create($app, self::PROFILE, [
            'out_trade_no' => $outTradeNo,
            'amount' => $amount,
            'subject' => $subject,
            'attach' => $attach,
            'return_url' => $returnUrl,
            'client_ip' => $clientIp,
        ], $paymentContext);
        $payment = $this->cashier->pay($app, $session->id, $method);
        $handover = $payment['pay'];

        return [
            'payment_no' => $payment['payment_no'],
            'channel' => $payment['channel'],
            'params' => $handover instanceof PaymentHandover ? $handover->jsonSerialize() : [],
        ];
    }
}
