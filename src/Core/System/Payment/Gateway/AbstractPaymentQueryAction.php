<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * The common execution of a payment query: record the channel-specific request, run it, record the
 * answer and expose the typed result. Subclasses only extract identifiers and call their api.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractPaymentQueryAction extends AbstractPaymentAction
{
    final public function capability(): PaymentCapability
    {
        return PaymentCapability::QUERY;
    }

    final public function supports($request): bool
    {
        return $this->isSupported($request, PaymentQuery::class);
    }

    final public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $this->recordRequest($model, $this->requestPayload($model));

        try {
            $result = $this->query($model);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($this->api()->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, $result->raw);
        $this->setResult($model, $result);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function requestPayload(PaymentGatewayModel $model): array;

    abstract protected function query(PaymentGatewayModel $model): QueryResult;

    /**
     * The answer of the last capture, which is where channels that use their own object id keep it.
     *
     * @return array<string, mixed>
     */
    final protected function lastChannelResponse(PaymentGatewayModel $model): array
    {
        $last = $this->value($model, PaymentGatewayModel::LAST_CHANNEL_RESPONSE);

        return \is_array($last) ? $last : [];
    }
}
