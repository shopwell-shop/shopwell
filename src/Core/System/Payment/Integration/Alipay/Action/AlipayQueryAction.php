<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Action;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentQueryAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;

/**
 * Asks alipay what happened to a trade whose notification never arrived.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayQueryAction extends AbstractPaymentQueryAction
{
    protected string $apiClass = AlipayApi::class;

    protected function requestPayload(PaymentGatewayModel $model): array
    {
        return [
            'out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            'currency' => strtoupper($this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY')),
        ];
    }

    protected function query(PaymentGatewayModel $model): QueryResult
    {
        $payload = $this->requestPayload($model);

        return $this->api()->query((string) $payload['out_trade_no'], (string) $payload['currency']);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }
}
