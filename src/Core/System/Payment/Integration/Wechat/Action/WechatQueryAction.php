<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Action;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentQueryAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;

/**
 * Asks wechat pay what happened to a trade whose notification never arrived.
 *
 * @internal
 */
#[Package('framework')]
final class WechatQueryAction extends AbstractPaymentQueryAction
{
    protected string $apiClass = WechatApi::class;

    protected function requestPayload(PaymentGatewayModel $model): array
    {
        return ['out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO)];
    }

    protected function query(PaymentGatewayModel $model): QueryResult
    {
        return $this->api()->query((string) $this->requestPayload($model)['out_trade_no']);
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }
}
