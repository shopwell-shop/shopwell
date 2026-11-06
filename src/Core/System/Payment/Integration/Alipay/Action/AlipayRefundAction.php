<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Refunds a payment at alipay. Alipay settles a refund synchronously, so the refund is finished right
 * after the answer.
 *
 * The `out_request_no` carries the refund number of the platform: it makes the call idempotent at
 * alipay, which is what a retry after an unanswered call needs - the interface refunds a trade once
 * per number, not once per call.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayRefundAction extends AbstractPaymentAction
{
    protected string $apiClass = AlipayApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::REFUND;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        if ($request instanceof RefundQuery) {
            $this->query($request, $model, $api);

            return;
        }

        $currency = $this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY');
        $amount = $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency);
        $refundNo = $this->stringValue($model, PaymentGatewayModel::REFUND_NO);

        $content = [
            // the payment number of the platform is the merchant order number alipay knows
            'out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            'refund_amount' => $amount,
            'out_request_no' => $refundNo,
        ];

        $reason = $this->stringValue($model, PaymentGatewayModel::REASON);

        if ($reason !== '') {
            $content['refund_reason'] = $reason;
        }

        $this->recordRequest($model, $content);

        try {
            $answer = $api->refundPayment($content);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage());
        }

        $tradeNo = $answer['trade_no'] ?? null;

        if (!\is_string($tradeNo) || $tradeNo === '') {
            // alipay answers its default response when it rejects the call
            $this->recordResponse($model, ['error' => 'the interface rejected the refund call']);

            throw PaymentGatewayException::gatewayError($api->channel(), 'the interface did not answer a trade number');
        }

        $this->recordResponse($model, $answer);

        $this->setResult($model, new RefundResult(
            status: PaymentRefundStates::STATE_FINISHED,
            outRefundId: $tradeNo,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class) || $this->isSupported($request, RefundQuery::class);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }

    private function query(RefundQuery $request, PaymentGatewayModel $model, AlipayApi $api): void
    {
        $answer = $api->queryRefund($this->stringValue($model, PaymentGatewayModel::PAYMENT_NO), $this->stringValue($model, PaymentGatewayModel::REFUND_NO));
        $this->recordResponse($model, $answer);
        $status = (string) ($answer['refund_status'] ?? '');

        $id = $answer['trade_no'] ?? $request->outRefundId;
        $this->setResult($model, new RefundResult(
            status: $status === 'REFUND_SUCCESS' ? PaymentRefundStates::STATE_FINISHED : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: \is_string($id) ? $id : null,
        ));
    }
}
