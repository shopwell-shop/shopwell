<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PayResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
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

    /**
     * Paypal cuts the description of a purchase unit at 127 characters.
     */
    private const MAX_DESCRIPTION_LENGTH = 127;

    protected string $apiClass = PaypalApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::CAPTURE;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $currency = strtoupper($this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'));
        // the payment number of the platform is what paypal hands back as the custom id, the trade
        // number of the app as the invoice id - both are needed to match the webhook
        $paymentNo = $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO);

        $order = [
            'amount' => $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency),
            'currency' => $currency,
            'payment_no' => $paymentNo,
            'invoice_id' => $this->stringValue($model, PaymentGatewayModel::OUT_TRADE_NO),
            'description' => mb_substr($this->stringValue($model, PaymentGatewayModel::SUBJECT), 0, self::MAX_DESCRIPTION_LENGTH),
            'return_url' => $api->returnUrl(),
            'brand_name' => $api->brandName(),
        ];

        $this->recordRequest($model, [
            'intent' => 'CAPTURE',
            'currency' => $currency,
            'amount' => $order['amount'],
            'payment_no' => $paymentNo,
        ]);

        try {
            $answer = $api->createOrder($order);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (isset($answer['error'])) {
            $this->recordResponse($model, $answer);

            throw PaymentException::gatewayError(
                $api->channel(),
                PaypalApi::rejectionMessageOf($answer) ?? 'the channel rejected the order'
            );
        }

        $orderId = $answer['id'] ?? null;

        if (!\is_string($orderId) || $orderId === '') {
            $this->recordResponse($model, $answer);

            throw PaymentException::gatewayError($api->channel(), 'the channel did not answer an order');
        }

        $this->recordResponse($model, [
            'id' => $orderId,
            'status' => $answer['status'] ?? null,
            'body' => $answer,
        ]);

        $this->setResult($model, new PayResult($this->payParams($api->setting(self::SETTING_FLOW), $orderId, $answer)));
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

    /**
     * @param array<string, mixed> $answer the order of the interface
     */
    private function payParams(?string $flow, string $orderId, array $answer): PayParams
    {
        if (strtolower(trim((string) $flow)) === self::FLOW_SDK) {
            return new PayParams(
                flow: PaymentFlow::SDK,
                extra: ['order_id' => $orderId]
            );
        }

        $approveUrl = PaypalApi::approveUrlOf($answer);

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
}
