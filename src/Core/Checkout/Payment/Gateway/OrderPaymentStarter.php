<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\Api\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
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
        private readonly PlatformPaymentApp $platformApp,
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentOrderCapture $paymentOrderCapture,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @throws PaymentException
     *
     * @return array{order: PaymentOrderEntity, result: PayResult}
     */
    public function start(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        PaymentChannel $channel,
        PaymentAppEntity $app,
        string $returnUrl
    ): array {
        // the app is the one the binding of the payment method names: the money is collected on its
        // merchant account. For the tenant of the platform the first payment additionally authorizes
        // the interface and offers the channel, so the shop's own payments need no onboarding by hand
        $app = $this->platformApp->authorizeFor($app, $channel);

        $existing = $this->paymentOrderService->findLatestByOrderTransaction($transaction->getId());

        if ($existing instanceof PaymentOrderEntity && $this->paymentOrderService->isPayable($existing)) {
            // the channel already answered for this payment: the payer only has to be handed over
            // again, the same way the app would do it with the parameters of the first capture
            $params = $this->restorePayParams($existing, $channel);

            if ($params instanceof PayParams) {
                return [
                    'order' => $existing,
                    'result' => new PayResult($params, $existing->outPaymentNo),
                ];
            }
        }

        // the create is idempotent on (app, out trade number): when the order exists but the
        // channel parameters could not be restored, the very same payment order is handed back and
        // only the capture is repeated
        $paymentOrder = $this->paymentOrderService->create(
            $app,
            $this->requestFor($order, $transaction, $channel, $returnUrl),
            $order,
            $transaction
        );

        return [
            'order' => $paymentOrder,
            'result' => $this->paymentOrderCapture->capture($app, $paymentOrder),
        ];
    }

    /**
     * The url the payer has to be sent to for the answer of the channel.
     *
     * @throws PaymentException
     */
    public function handOver(PaymentOrderEntity $order, PayResult $result): string
    {
        $params = $result->params;

        return match ($params->flow) {
            PaymentFlow::REDIRECT => $params->payUrl ?? $this->htmlFormUrl($order, $params),
            PaymentFlow::QRCODE => $this->router->generate(self::ROUTE_QR_CODE, ['paymentNo' => $order->paymentNo]),
            // a channel that is pulled up by an sdk cannot be opened from a browser; binding one to a
            // checkout payment method is a configuration error, not something the payer can fix
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            PaymentFlow::SDK => throw PaymentException::channelNotAvailableInStorefront($order->channelTechnicalName),
        };
    }

    /**
     * @throws PaymentException
     */
    private function htmlFormUrl(PaymentOrderEntity $order, PayParams $params): string
    {
        if ($params->htmlForm === null) {
            // neither a url nor a form: the channel did not say how the payer gets to it
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::gatewayResultMissing($order->channelTechnicalName);
        }

        // a self submitting form is rendered by the storefront, the checkout only names the page
        return $this->router->generate(self::ROUTE_FORM, ['paymentNo' => $order->paymentNo]);
    }

    /**
     * @throws PaymentException
     */
    private function requestFor(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        PaymentChannel $channel,
        string $returnUrl
    ): PaymentOrderRequest {
        $currency = $order->getCurrency();

        if (!$currency instanceof CurrencyEntity) {
            // the amount is converted to the smallest unit of the currency, which cannot be guessed
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::missingParameter('currency');
        }

        return new PaymentOrderRequest(
            // the transaction is the idempotency key: one order, one payment - a retry of the same
            // checkout must not open a second trade at the interface
            outTradeNo: $transaction->getId(),
            channel: $channel->technicalName,
            amount: $this->toSmallestUnit($transaction->getAmount()->getTotalPrice(), $currency),
            currency: $currency->getIsoCode(),
            subject: $order->getOrderNumber() ?? $order->getId(),
            returnUrl: $returnUrl,
        );
    }

    private function toSmallestUnit(float $totalPrice, CurrencyEntity $currency): int
    {
        return (int) round($totalPrice * (10 ** $currency->getItemRounding()->getDecimals()));
    }

    /**
     * The parameters of the answer the channel already gave.
     *
     * The shape of that answer is knowledge of the interface, not of the checkout, so the decode is
     * asked from the interface the channel belongs to. Null means the interface cannot hand the payer
     * over again and the payment is captured anew.
     */
    private function restorePayParams(PaymentOrderEntity $order, PaymentChannel $channel): ?PayParams
    {
        $response = $this->lastCaptureResponse($order);

        if ($response === []) {
            return null;
        }

        return $this->providers
            ->get($channel->providerTechnicalName)
            ->payParamsFromResponse($response);
    }

    /**
     * The answer of the last capture that did not fail before the channel answered. It is where the
     * parameters of the hand over are found again, because a payment order stores the order data but
     * not how the channel answered it.
     *
     * @return array<string, mixed>
     */
    private function lastCaptureResponse(PaymentOrderEntity $order): array
    {
        foreach (array_reverse($order->transactions ?? []) as $transaction) {
            if (!$transaction instanceof PaymentTransactionEntity || $transaction->type !== PaymentTransactionEntity::TYPE_CAPTURE) {
                continue;
            }

            $response = $transaction->responsePayload;

            if (\is_array($response) && $response !== []) {
                return $response;
            }
        }

        return [];
    }
}
