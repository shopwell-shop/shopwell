<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Action;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentQueryAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\Payload;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;

/**
 * Asks stripe what happened to a payment whose notification never arrived.
 *
 * @internal
 */
#[Package('framework')]
final class StripeQueryAction extends AbstractPaymentQueryAction
{
    protected string $apiClass = StripeApi::class;

    protected function requestPayload(PaymentGatewayModel $model): array
    {
        $last = $this->lastChannelResponse($model);
        $id = Payload::string($last, 'id');
        $intent = Payload::string($last, 'payment_intent')
            ?? (str_starts_with((string) $id, 'pi_') ? $id : null);

        return [
            // the intent wins whenever there is one, the session is the fallback of the redirect flow
            'session_id' => $intent === null ? $id : null,
            'payment_intent_id' => $intent,
        ];
    }

    protected function query(PaymentGatewayModel $model): QueryResult
    {
        $payload = $this->requestPayload($model);

        return $this->api()->query($payload['session_id'], $payload['payment_intent_id']);
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }
}
