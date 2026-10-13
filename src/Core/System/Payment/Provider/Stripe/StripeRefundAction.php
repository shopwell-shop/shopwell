<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentRefundStates;
use Stripe\Refund as StripeRefund;

/**
 * Refunds a payment at stripe.
 *
 * Stripe refunds the payment intent of the trade, which is what the payment number of the channel holds
 * here. The refund number of the platform is sent along as the idempotency key and as metadata, so a
 * repeated call creates a single refund and the notification of stripe can be matched to the refund.
 *
 * @internal
 */
#[Package('framework')]
final class StripeRefundAction extends AbstractPaymentAction
{
    final public const STATUS_SUCCEEDED = 'succeeded';

    /**
     * The statuses stripe reports for a refund that will never happen.
     */
    final public const FAILED_STATUSES = ['failed', 'canceled'];

    /**
     * Stripe only accepts a reason from a fixed set, anything else goes into the metadata.
     */
    final public const REASON_REQUESTED_BY_CUSTOMER = 'requested_by_customer';

    protected string $apiClass = StripeApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $paymentIntentId = $this->stringValue($model, PaymentGatewayModel::OUT_PAYMENT_NO);

        if ($paymentIntentId === '') {
            throw PaymentException::gatewayError(
                $api->channel(),
                'the payment has no payment intent at stripe yet and therefore cannot be refunded'
            );
        }

        $refundNo = $this->stringValue($model, PaymentGatewayModel::REFUND_NO);

        $metadata = [StripeNotificationExtractor::METADATA_REFUND_NO => $refundNo];

        $reason = $this->stringValue($model, PaymentGatewayModel::REASON);
        if ($reason !== '') {
            $metadata[StripeNotificationExtractor::METADATA_REASON] = mb_substr($reason, 0, 500);
        }

        $payload = [
            'payment_intent' => $paymentIntentId,
            'amount' => $this->intValue($model, PaymentGatewayModel::AMOUNT),
            'metadata' => $metadata,
            'reason' => self::REASON_REQUESTED_BY_CUSTOMER,
        ];

        $this->recordRequest($model, [
            'payment_intent' => $paymentIntentId,
            'amount' => $payload['amount'],
            'metadata' => $metadata,
        ]);

        try {
            // the refund number of the platform makes the call idempotent on the side of stripe: a
            // repeated request answers the very same refund instead of creating a second one
            $refund = $api->client()->refunds->create($payload, ['idempotency_key' => $refundNo]);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (!$refund instanceof StripeRefund) {
            throw PaymentException::gatewayError($api->channel(), 'the channel did not answer a refund');
        }

        $status = (string) $refund->status;

        $this->recordResponse($model, ['id' => $refund->id, 'status' => $status]);

        if (\in_array($status, self::FAILED_STATUSES, true)) {
            throw PaymentException::gatewayError(
                $api->channel(),
                \sprintf('the channel refused the refund with the status "%s"', $status)
            );
        }

        $this->setResult($model, new RefundResult(
            status: $status === self::STATUS_SUCCEEDED
                ? PaymentRefundStates::STATE_FINISHED
                : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: $refund->id,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class);
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }
}
