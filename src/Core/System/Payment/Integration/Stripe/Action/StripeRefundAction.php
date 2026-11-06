<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentRefundRejectedException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

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

        $paymentIntentId = $this->stringValue($model, PaymentGatewayModel::OUT_PAYMENT_NO);

        if ($paymentIntentId === '') {
            throw PaymentGatewayException::gatewayError(
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
            $answer = $api->createRefund($payload, $refundNo);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (isset($answer['error'])) {
            $this->recordResponse($model, $answer);

            $code = StripeApi::rejectionCodeOf($answer);
            if (\in_array($code, ['amount_too_large', 'charge_already_refunded', 'charge_not_refundable', 'resource_missing'], true)) {
                throw PaymentRefundRejectedException::rejected($api->channel(), StripeApi::rejectionMessageOf($answer) ?? $code);
            }

            throw PaymentGatewayException::gatewayError(
                $api->channel(),
                StripeApi::rejectionMessageOf($answer) ?? 'the channel rejected the refund'
            );
        }

        $status = (string) ($answer['status'] ?? '');
        $outRefundId = (string) ($answer['id'] ?? '');

        $this->recordResponse($model, ['id' => $outRefundId, 'status' => $status]);

        if (\in_array($status, self::FAILED_STATUSES, true)) {
            throw PaymentRefundRejectedException::rejected(
                $api->channel(),
                \sprintf('the channel refused the refund with the status "%s"', $status)
            );
        }

        $this->setResult($model, new RefundResult(
            status: $status === self::STATUS_SUCCEEDED
                ? PaymentRefundStates::STATE_FINISHED
                : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: $outRefundId,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class) || $this->isSupported($request, RefundQuery::class);
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }

    private function query(RefundQuery $request, PaymentGatewayModel $model, StripeApi $api): void
    {
        $answer = $api->queryRefund($this->stringValue($model, PaymentGatewayModel::OUT_PAYMENT_NO), $this->stringValue($model, PaymentGatewayModel::REFUND_NO), $request->outRefundId);
        $this->recordResponse($model, $answer);
        $status = (string) ($answer['status'] ?? '');
        if (\in_array($status, self::FAILED_STATUSES, true)) {
            throw PaymentRefundRejectedException::rejected($api->channel(), 'The channel confirmed a failed refund: ' . $status);
        }

        $id = $answer['id'] ?? $request->outRefundId;
        $this->setResult($model, new RefundResult(
            status: $status === self::STATUS_SUCCEEDED ? PaymentRefundStates::STATE_FINISHED : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: \is_string($id) ? $id : null,
        ));
    }
}
