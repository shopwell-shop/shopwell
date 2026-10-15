<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Asks alipay what happened to a trade whose notification never arrived.
 *
 * The query runs on the merchant order number alone, which alipay knows from the capture, so the
 * reconciliation needs nothing but the payment order itself. The answer says what the platform
 * should do with the order - see {@see QueryResult}; a trade alipay does not know answers `absent`
 * instead of failing, which is what the expiry of the order waits for.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = AlipayApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $outTradeNo = $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO);
        $currency = strtoupper($this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'));

        $this->recordRequest($model, [
            'out_trade_no' => $outTradeNo,
            'currency' => $currency,
        ]);

        try {
            $result = $api->query($outTradeNo, $currency);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        $this->recordResponse($model, $result->raw);

        $this->setResult($model, $result);
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentQuery::class);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }
}
