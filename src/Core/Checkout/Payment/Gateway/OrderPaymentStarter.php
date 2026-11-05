<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Symfony\Component\Routing\RouterInterface;

/**
 * Opens the payment of a checkout order and hands the payer over to its channel.
 *
 * A checkout order already exists when it is paid, so the payment order is created for it instead of
 * by an app: the transaction id is the out trade number, which makes reopening the payment idempotent -
 * the payer who comes back, reloads or switches the device gets the payment order that already exists
 * instead of a second one. Only a payment order that can still be paid is reused; one that was paid or
 * closed belongs to another attempt and a fresh order is created for this one.
 *
 * The class answers with a url rather than a response on purpose: the checkout layer knows the routes
 * of the storefront, but building http responses is the job of the caller that owns the request.
 *
 * @internal
 */
#[Package('checkout')]
final class OrderPaymentStarter
{
    /**
     * The middle pages are part of this handler's contract with the storefront: the checkout opens
     * the payment, the storefront renders the form or the qr code the channel answered.
     *
     * @phpstan-ignore shopwell.storefrontRouteUsage (The checkout hands the payer over to the storefront middle page of the gateway it opened.)
     */
    final public const ROUTE_FORM = 'frontend.payment.gateway.form';

    /**
     * @phpstan-ignore shopwell.storefrontRouteUsage (The checkout hands the payer over to the storefront middle page of the gateway it opened.)
     */
    final public const ROUTE_QR_CODE = 'frontend.payment.gateway.qr';

    public function __construct(
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentOrderStarter $paymentOrderStarter,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @throws PaymentException
     *
     * @return array{order: PaymentOrderEntity, result: PaymentStartResult}
     */
    public function start(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        PaymentRoute $route,
        PaymentAppEntity $app,
        string $returnUrl,
        PaymentContext $paymentContext,
    ): array {
        $paymentOrder = $this->paymentOrderService->create(
            $app,
            $this->requestFor($order, $transaction, $returnUrl),
            $order,
            $transaction,
        );

        return [
            'order' => $paymentOrder,
            'result' => $this->paymentOrderStarter->start($app, $paymentOrder, $route),
        ];
    }

    /**
     * The channel answer a storefront middle page renders again after a redirect.
     *
     * @throws PaymentException
     */
    public function payParams(PaymentOrderEntity $order): PaymentHandover
    {
        $params = $this->paymentOrderStarter->replay($order)?->params;

        if (!$params instanceof PaymentHandover) {
            throw CheckoutPaymentException::gatewayResultMissing($order->activeAttempt->channelTechnicalName ?? '');
        }

        return $params;
    }

    /**
     * The url the payer has to be sent to for the answer of the channel.
     *
     * @throws CheckoutPaymentException
     */
    public function handOver(PaymentOrderEntity $order, PaymentStartResult $result): string
    {
        $params = $result->params;

        return match ($params->flow) {
            PaymentInteraction::REDIRECT => $params->payUrl ?? $this->htmlFormUrl($order, $params),
            PaymentInteraction::QRCODE => $this->router->generate(self::ROUTE_QR_CODE, ['orderId' => $order->id, 'paymentNo' => $order->paymentNo]),
            // a channel that is pulled up by an sdk cannot be opened from a browser; binding one to a
            // checkout payment method is a configuration error, not something the payer can fix
            default => throw CheckoutPaymentException::channelNotAvailableInStorefront($order->activeAttempt->channelTechnicalName ?? ''),
        };
    }

    /**
     * @throws CheckoutPaymentException
     */
    private function htmlFormUrl(PaymentOrderEntity $order, PaymentHandover $params): string
    {
        if ($params->htmlForm === null) {
            // neither a url nor a form: the channel did not say how the payer gets to it
            throw CheckoutPaymentException::gatewayResultMissing($order->activeAttempt->channelTechnicalName ?? '');
        }

        // a self submitting form is rendered by the storefront, the checkout only names the page
        return $this->router->generate(self::ROUTE_FORM, ['orderId' => $order->id, 'paymentNo' => $order->paymentNo]);
    }

    /**
     * @throws PaymentException
     */
    private function requestFor(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        string $returnUrl,
    ): PaymentOrderRequest {
        $currency = $order->getCurrency();

        if (!$currency instanceof CurrencyEntity) {
            // the amount is converted to the smallest unit of the currency, which cannot be guessed
            throw CheckoutPaymentException::missingRequestParameter('currency');
        }

        return new PaymentOrderRequest(
            // the transaction is the idempotency key: one order, one payment - a retry of the same
            // checkout must not open a second trade at the interface
            outTradeNo: $transaction->getId(),
            amount: $this->toSmallestUnit($transaction->getAmount()->getTotalPrice(), $currency),
            currency: $currency->getIsoCode(),
            subject: $order->getOrderNumber() ?? $order->getId(),
            returnUrl: $returnUrl,
        );
    }

    /**
     * The gateway counts in the smallest unit of the ISO currency, which is not necessarily the number
     * of decimals the shop rounds its prices with: a shop that displays and rounds to whole yuan still
     * pays in fen, and a conversion by the item rounding would charge a hundredth of the price.
     */
    private function toSmallestUnit(float $totalPrice, CurrencyEntity $currency): int
    {
        return CurrencyAmountConverter::fromFloat($totalPrice, $currency->getIsoCode());
    }
}
