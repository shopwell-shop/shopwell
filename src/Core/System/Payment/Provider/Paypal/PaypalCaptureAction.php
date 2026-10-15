<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Models\AmountWithBreakdown;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\LinkDescription;
use PaypalServerSdkLib\Models\Order;
use PaypalServerSdkLib\Models\OrderApplicationContext;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;

/**
 * Creates a paypal order and answers the app with what it needs to send the payer to paypal.
 *
 * Paypal has no h5 product: the order carries an `approve` link the app redirects to, which is the
 * redirect flow. With the setting `flow=sdk` the order id is handed to the app instead, so a browser
 * can approve the very same order with the paypal javascript sdk.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalCaptureAction extends AbstractPaymentAction
{
    /**
     * The setting that selects how the payer is handed over: `redirect` or `sdk`.
     */
    final public const SETTING_FLOW = 'flow';

    final public const FLOW_REDIRECT = 'redirect';
    final public const FLOW_SDK = 'sdk';

    final public const REL_APPROVE = 'approve';

    /**
     * Paypal cuts the description of a purchase unit at 127 characters.
     */
    private const MAX_DESCRIPTION_LENGTH = 127;

    protected string $apiClass = PaypalApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $currency = strtoupper($this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'));
        // the payment number of the platform is what paypal hands back as the custom id, the trade
        // number of the app as the invoice id - both are needed to match the webhook
        $paymentNo = $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO);

        $purchaseUnit = new PurchaseUnitRequest(new AmountWithBreakdown(
            $currency,
            $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency)
        ));
        $purchaseUnit->setCustomId($paymentNo);
        $purchaseUnit->setInvoiceId($this->stringValue($model, PaymentGatewayModel::OUT_TRADE_NO));

        $description = mb_substr($this->stringValue($model, PaymentGatewayModel::SUBJECT), 0, self::MAX_DESCRIPTION_LENGTH);
        if ($description !== '') {
            $purchaseUnit->setDescription($description);
        }

        $orderRequest = new OrderRequest(CheckoutPaymentIntent::CAPTURE, [$purchaseUnit]);

        $applicationContext = new OrderApplicationContext();
        $applicationContext->setUserAction('PAY_NOW');
        $applicationContext->setShippingPreference('NO_SHIPPING');

        $returnUrl = $api->returnUrl();
        if ($returnUrl !== '') {
            $applicationContext->setReturnUrl($returnUrl);
            $applicationContext->setCancelUrl($returnUrl);
        }

        $brandName = $api->brandName();
        if ($brandName !== '') {
            $applicationContext->setBrandName(mb_substr($brandName, 0, 127));
        }

        $orderRequest->setApplicationContext($applicationContext);

        $payload = ['body' => $orderRequest, 'prefer' => 'return=representation'];

        $this->recordRequest($model, [
            'intent' => CheckoutPaymentIntent::CAPTURE,
            'currency' => $currency,
            'amount' => $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency),
            'payment_no' => $paymentNo,
        ]);

        try {
            $response = $api->client()->getOrdersController()->createOrder($payload);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        $order = $response->getResult();

        if (!$order instanceof Order || $order->getId() === null) {
            $this->recordResponse($model, ['body' => $response->getBody()]);

            throw PaymentException::gatewayError($api->channel(), 'the channel did not answer an order');
        }

        $this->recordResponse($model, [
            'id' => $order->getId(),
            'status' => $order->getStatus(),
            'body' => $response->getBody(),
        ]);

        $this->setResult($model, new PayResult($this->payParams($api->setting(self::SETTING_FLOW), $order)));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Capture::class);
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }

    private function payParams(?string $flow, Order $order): PayParams
    {
        $orderId = (string) $order->getId();

        if (strtolower(trim((string) $flow)) === self::FLOW_SDK) {
            return new PayParams(
                flow: PaymentFlow::SDK,
                extra: ['order_id' => $orderId]
            );
        }

        $approveUrl = $this->approveUrlOf($order);

        if ($approveUrl === null) {
            throw PaymentException::gatewayError(PaypalApi::PROVIDER, 'the channel did not answer an approval url');
        }

        return new PayParams(
            flow: PaymentFlow::REDIRECT,
            payUrl: $approveUrl,
            httpMethod: 'GET',
            extra: ['order_id' => $orderId]
        );
    }

    private function approveUrlOf(Order $order): ?string
    {
        foreach ($order->getLinks() ?? [] as $link) {
            if (!$link instanceof LinkDescription || $link->getRel() !== self::REL_APPROVE) {
                continue;
            }

            $href = $link->getHref();

            if ($href !== '') {
                return $href;
            }
        }

        return null;
    }
}
