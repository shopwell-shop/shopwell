<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Asks wechat pay what happened to a trade whose notification never arrived.
 *
 * The query runs on the merchant order number alone, which wechat knows from the capture, so the
 * reconciliation needs nothing but the payment order itself. The answer says what the platform
 * should do with the order - see {@see QueryResult}; a trade wechat does not know answers `absent`
 * instead of failing, which is what the expiry of the order waits for.
 *
 * @internal
 */
#[Package('framework')]
final class WechatQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = WechatApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::QUERY;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $outTradeNo = $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO);

        $this->recordRequest($model, [
            'out_trade_no' => $outTradeNo,
        ]);

        try {
            $result = $api->query($outTradeNo);
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

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }
}
