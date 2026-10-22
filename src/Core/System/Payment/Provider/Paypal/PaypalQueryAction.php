<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\Payload;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Asks paypal what happened to an order whose notification never arrived.
 *
 * Paypal is found again through the order id of its own capture answer - the platform captures
 * nothing itself, paypal does, so the capture id only ever arrives with the notification that is
 * missing here. The answer says what the platform should do with the order, see {@see QueryResult};
 * a resource paypal does not know answers `absent` instead of failing, which is what the expiry of
 * the order waits for.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = PaypalApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::QUERY;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $orderId = Payload::string($this->lastChannelResponse($model), 'id');

        $this->recordRequest($model, [
            'order_id' => $orderId,
        ]);

        try {
            $result = $api->query(captureId: null, orderId: $orderId);
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

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }

    /**
     * The answer of the last capture, which is where the order id of paypal is found.
     *
     * @return array<string, mixed>
     */
    private function lastChannelResponse(PaymentGatewayModel $model): array
    {
        $last = $this->value($model, PaymentGatewayModel::LAST_CHANNEL_RESPONSE);

        return \is_array($last) ? $last : [];
    }
}
