<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Action;

use PaypalServerSdkLib\Models\RefundStatus;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
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
    final public const FAILED_STATUSES = [RefundStatus::FAILED, RefundStatus::CANCELLED];

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

        $captureId = $this->stringValue($model, PaymentGatewayModel::OUT_PAYMENT_NO);

        if ($captureId === '') {
            throw PaymentException::gatewayError(
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

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (isset($answer['error'])) {
            $this->recordResponse($model, $answer);

            throw PaymentException::gatewayError(
                $api->channel(),
                PaypalApi::rejectionMessageOf($answer) ?? 'the channel rejected the refund'
            );
        }

        $status = (string) ($answer['status'] ?? '');
        $outRefundId = $answer['id'] ?? null;
        $outRefundId = \is_string($outRefundId) ? $outRefundId : null;

        $this->recordResponse($model, ['id' => $outRefundId, 'status' => $status]);

        if (\in_array($status, self::FAILED_STATUSES, true)) {
            throw PaymentException::gatewayError(
                $api->channel(),
                \sprintf('the channel refused the refund with the status "%s"', $status)
            );
        }

        $this->setResult($model, new RefundResult(
            status: $status === RefundStatus::COMPLETED
                ? PaymentRefundStates::STATE_FINISHED
                : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: $outRefundId,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class);
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }
}
