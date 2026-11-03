<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Action;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentQueryAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\Payload;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;

/**
 * Asks paypal what happened to an order whose notification never arrived.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalQueryAction extends AbstractPaymentQueryAction
{
    protected string $apiClass = PaypalApi::class;

    protected function requestPayload(PaymentGatewayModel $model): array
    {
        return ['order_id' => Payload::string($this->lastChannelResponse($model), 'id')];
    }

    protected function query(PaymentGatewayModel $model): QueryResult
    {
        return $this->api()->query(captureId: null, orderId: $this->requestPayload($model)['order_id']);
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }
}
