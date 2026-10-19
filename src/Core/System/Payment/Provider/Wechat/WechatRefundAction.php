<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Refunds a wechat pay trade (api v3).
 *
 * Wechat settles a refund either right away or asynchronously; in the second case the refund stays in
 * processing until the notification of the channel confirms it.
 *
 * @internal
 */
#[Package('framework')]
final class WechatRefundAction extends AbstractPaymentAction
{
    final public const URI_REFUND = 'v3/refund/domestic/refunds';

    final public const STATUS_SUCCESS = 'SUCCESS';

    final public const STATUS_PROCESSING = 'PROCESSING';

    /**
     * The states wechat reports for a refund that will never happen.
     */
    final public const FAILED_STATES = ['CLOSED', 'ABNORMAL'];

    protected string $apiClass = WechatApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::REFUND;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $payload = [
            // wechat refunds the merchant order number the trade was created with
            'out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            'out_refund_no' => $this->stringValue($model, PaymentGatewayModel::REFUND_NO),
            'notify_url' => $api->notifyUrl(),
            'amount' => [
                'refund' => $this->intValue($model, PaymentGatewayModel::AMOUNT),
                'total' => $this->intValue($model, PaymentGatewayModel::ORDER_AMOUNT),
                'currency' => $this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'),
            ],
        ];

        $reason = $this->stringValue($model, PaymentGatewayModel::REASON);
        if ($reason !== '') {
            $payload['reason'] = $reason;
        }

        $this->recordRequest($model, $payload);

        try {
            $answer = $api->post(self::URI_REFUND, $payload);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        $this->recordResponse($model, $answer);

        $status = \is_string($answer['status'] ?? null) ? $answer['status'] : '';

        if (\in_array($status, self::FAILED_STATES, true)) {
            throw PaymentException::gatewayError($api->channel(), \sprintf('the channel refused the refund with the status "%s"', $status));
        }

        $outRefundId = \is_string($answer['refund_id'] ?? null) ? $answer['refund_id'] : null;

        $this->setResult($model, new RefundResult(
            status: $status === self::STATUS_SUCCESS ? PaymentRefundStates::STATE_FINISHED : PaymentRefundStates::STATE_PROCESSING,
            outRefundId: $outRefundId,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Refund::class);
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }
}
