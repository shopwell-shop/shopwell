<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Model\AlipayTradeRefundModel;
use Alipay\OpenAPISDK\Model\AlipayTradeRefundResponseModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentRefundStates;

/**
 * Refunds a payment at alipay. Alipay settles a refund synchronously, so the refund is finished right
 * after the answer.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayRefundAction extends AbstractPaymentAction
{
    protected string $apiClass = AlipayApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $currency = $this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY');
        $amount = $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency);
        $refundNo = $this->stringValue($model, PaymentGatewayModel::REFUND_NO);

        $refund = new AlipayTradeRefundModel();
        // the payment number of the platform is the merchant order number alipay knows
        $refund->setOutTradeNo($this->stringValue($model, PaymentGatewayModel::PAYMENT_NO));
        $refund->setRefundAmount($amount);
        $refund->setOutRequestNo($refundNo);

        $reason = $this->stringValue($model, PaymentGatewayModel::REASON);
        if ($reason !== '') {
            $refund->setRefundReason($reason);
        }

        $this->recordRequest($model, [
            'out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            'out_request_no' => $refundNo,
            'refund_amount' => $amount,
        ]);

        try {
            $answer = $api->tradeApi()->refund($refund);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (!$answer instanceof AlipayTradeRefundResponseModel) {
            // alipay answers its default response when it rejects the call
            $this->recordResponse($model, ['error' => 'the interface rejected the refund call']);

            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a trade number');
        }

        $this->recordResponse($model, ['trade_no' => $answer->getTradeNo(), 'fund_change' => $answer->getFundChange()]);

        $this->setResult($model, new RefundResult(
            status: PaymentRefundStates::STATE_FINISHED,
            outRefundId: $answer->getTradeNo(),
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }
}
