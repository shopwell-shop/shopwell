<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Models\Money;
use PaypalServerSdkLib\Models\Refund as PaypalRefund;
use PaypalServerSdkLib\Models\RefundRequest as PaypalRefundRequest;
use PaypalServerSdkLib\Models\RefundStatus;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentRefundStates;

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

        $body = new PaypalRefundRequest();
        $body->setAmount(new Money($currency, $amount));
        // the refund number of the platform is handed back as the custom id of the refund, which is
        // how the webhook of the channel is matched to the refund
        $body->setCustomId($refundNo);

        $reason = $this->stringValue($model, PaymentGatewayModel::REASON);
        if ($reason !== '') {
            $body->setNoteToPayer(mb_substr($reason, 0, 255));
        }

        $this->recordRequest($model, [
            'capture_id' => $captureId,
            'custom_id' => $refundNo,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        try {
            $response = $api->client()->getPaymentsController()->refundCapturedPayment([
                'captureId' => $captureId,
                'body' => $body,
                // the refund number of the platform makes the call idempotent on the side of paypal
                'paypalRequestId' => $refundNo,
                'prefer' => 'return=representation',
            ]);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        $refund = $response->getResult();

        if (!$refund instanceof PaypalRefund) {
            $this->recordResponse($model, ['body' => $response->getBody()]);

            throw PaymentException::gatewayError($api->channel(), 'the channel did not answer a refund');
        }

        $status = (string) $refund->getStatus();

        $this->recordResponse($model, ['id' => $refund->getId(), 'status' => $status]);

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
            outRefundId: $refund->getId(),
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
