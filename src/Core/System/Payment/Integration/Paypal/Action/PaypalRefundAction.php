<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentRefundRejectedException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Refunds a captured paypal payment.
 *
 * Paypal refunds the *capture* of a trade, not the order: that is why the payment number of the
 * channel - the capture id - is required here, and why a payment can only be refunded once paypal
 * confirmed the capture. A refund settles either right away or asynchronously, which is reported by
 * the status of the answer.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalRefundAction extends AbstractPaymentAction
{
    /**
     * The statuses paypal reports for a refund that will never happen.
     */
    final public const FAILED_STATUSES = [PaypalApi::REFUND_FAILED, PaypalApi::REFUND_CANCELLED];

    protected string $apiClass = PaypalApi::class;

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

        $captureId = $this->stringValue($model, PaymentGatewayModel::OUT_PAYMENT_NO);

        if ($captureId === '') {
            throw PaymentGatewayException::gatewayError(
                $api->channel(),
                'the payment has no capture at paypal yet and therefore cannot be refunded'
            );
        }

        $currency = strtoupper($this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'));
        $amount = $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency);
        $refundNo = $this->stringValue($model, PaymentGatewayModel::REFUND_NO);

        $refund = [
            'amount' => $amount,
            'currency' => $currency,
            'refund_no' => $refundNo,
            'reason' => $this->stringValue($model, PaymentGatewayModel::REASON),
        ];

        $this->recordRequest($model, [
            'capture_id' => $captureId,
            'custom_id' => $refundNo,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        try {
            $answer = $api->refundCapture($captureId, $refund, $refundNo);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (isset($answer['error'])) {
            $this->recordResponse($model, $answer);

            $code = PaypalApi::rejectionCodeOf($answer);
            if (\in_array($code, ['REFUND_AMOUNT_EXCEEDED', 'CAPTURE_FULLY_REFUNDED', 'REFUND_NOT_ALLOWED'], true)) {
                throw PaymentRefundRejectedException::rejected($api->channel(), PaypalApi::rejectionMessageOf($answer) ?? $code);
            }

            throw PaymentGatewayException::gatewayError(
                $api->channel(),
                PaypalApi::rejectionMessageOf($answer) ?? 'the channel rejected the refund'
            );
        }

        $status = (string) ($answer['status'] ?? '');
        $outRefundId = $answer['id'] ?? null;
        $outRefundId = \is_string($outRefundId) ? $outRefundId : null;

        $this->recordResponse($model, ['id' => $outRefundId, 'status' => $status]);

        if (\in_array($status, self::FAILED_STATUSES, true)) {
            throw PaymentRefundRejectedException::rejected(
                $api->channel(),
                \sprintf('the channel refused the refund with the status "%s"', $status)
            );
        }

        $this->setResult($model, new RefundResult(
            status: $status === PaypalApi::REFUND_COMPLETED
                ? PaymentRefundStates::STATE_FINISHED
                : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: $outRefundId,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class) || $this->isSupported($request, RefundQuery::class);
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }

    private function query(RefundQuery $request, PaymentGatewayModel $model, PaypalApi $api): void
    {
        $answer = $api->queryRefund($this->stringValue($model, PaymentGatewayModel::OUT_PAYMENT_NO), $this->stringValue($model, PaymentGatewayModel::REFUND_NO), $request->outRefundId);
        $this->recordResponse($model, $answer);
        $status = (string) ($answer['status'] ?? '');
        if (\in_array($status, self::FAILED_STATUSES, true)) {
            throw PaymentRefundRejectedException::rejected($api->channel(), 'The channel confirmed a failed refund: ' . $status);
        }

        $id = $answer['id'] ?? $request->outRefundId;
        $this->setResult($model, new RefundResult(
            status: $status === PaypalApi::REFUND_COMPLETED ? PaymentRefundStates::STATE_FINISHED : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: \is_string($id) ? $id : null,
        ));
    }
}
