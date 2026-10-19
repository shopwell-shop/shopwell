<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\Payload;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Asks stripe what happened to a payment whose notification never arrived.
 *
 * Stripe is found again through the answer of its own capture, which is either a payment intent (the
 * sdk flow, an id that starts with `pi_`) or a hosted checkout session (the redirect flow, an id that
 * starts with `cs_`) - a session only gets its intent when the payer confirmed it, so both have to be
 * asked for. The answer says what the platform should do with the order, see {@see QueryResult}; a
 * resource stripe does not know answers `absent` instead of failing, which is what the expiry of the
 * order waits for.
 *
 * @internal
 */
#[Package('framework')]
final class StripeQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = StripeApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $last = $this->lastChannelResponse($model);
        $id = Payload::string($last, 'id');
        $intent = Payload::string($last, 'payment_intent')
            ?? (str_starts_with((string) $id, 'pi_') ? $id : null);
        // the intent wins whenever there is one, the session is the fallback of the redirect flow
        $session = $intent === null ? $id : null;

        $this->recordRequest($model, [
            'session_id' => $session,
            'payment_intent_id' => $intent,
        ]);

        try {
            $result = $api->query($session, $intent);
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

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }

    /**
     * The answer of the last capture, which is where the session or the intent of stripe is found.
     *
     * @return array<string, mixed>
     */
    private function lastChannelResponse(PaymentGatewayModel $model): array
    {
        $last = $this->value($model, PaymentGatewayModel::LAST_CHANNEL_RESPONSE);

        return \is_array($last) ? $last : [];
    }
}
