<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Closes an alipay trade, the counterpart of the capture.
 *
 * The channel is asked by the merchant order number the trade was opened with, so this works on the
 * credentials of the merchant account alone - like querying and refunding, closing does not care which
 * channel opened the trade. A trade that cannot be closed because it is already paid is an answer of
 * its own, see {@see CloseResult}: treating it as a failure would leave a paid trade behind an order
 * the platform believes it closed.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayCloseAction extends AbstractPaymentAction
{
    final public const METHOD_CLOSE = 'alipay.trade.close';

    protected string $apiClass = AlipayApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $outTradeNo = $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO);
        $currency = $this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY');

        $this->recordRequest($model, ['method' => self::METHOD_CLOSE, 'out_trade_no' => $outTradeNo]);

        try {
            $result = $api->close($outTradeNo, $currency);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, ['status' => $result->status, 'channel_status' => $result->channelStatus]);

        $this->setResult($model, $result);
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentClose::class);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }
}
