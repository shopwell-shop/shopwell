<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Closes a wechat pay trade (api v3), the counterpart of the capture.
 *
 * The trade is closed by the merchant order number, so the merchant account alone is enough - like
 * querying and refunding, closing does not care which of the products opened the trade. A trade that
 * cannot be closed because it is paid is an answer of its own, see {@see CloseResult}.
 *
 * @internal
 */
#[Package('framework')]
final class WechatCloseAction extends AbstractPaymentAction
{
    protected string $apiClass = WechatApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::CLOSE;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $outTradeNo = $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO);

        $this->recordRequest($model, ['uri' => 'v3/pay/transactions/out-trade-no/{out_trade_no}/close', 'out_trade_no' => $outTradeNo]);

        try {
            $result = $api->close($outTradeNo);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, ['status' => $result->status, 'channel_status' => $result->channelStatus, 'raw' => $result->raw]);

        $this->setResult($model, $result);
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentClose::class);
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }
}
