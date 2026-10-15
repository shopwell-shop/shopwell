<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * The stripe api of one payment.
 *
 * Stripe takes the amount in the smallest unit of the currency, exactly like the platform stores it, so
 * no amount has to be converted for the api calls.
 *
 * The webhook is verified with the signing secret of the endpoint of the app, so every app verifies its
 * own notifications - an app may therefore run its own stripe account next to the one of the platform.
 *
 * @internal
 */
#[Package('framework')]
final class StripeApi extends AbstractPaymentApi
{
    final public const PROVIDER = 'stripe';

    final public const SECRET_KEY = 'secret_key';

    /**
     * The publishable key, only needed for the client side flow of the javascript sdk.
     */
    final public const PUBLISHABLE_KEY = 'publishable_key';

    /**
     * The signing secret of the webhook endpoint, `whsec_...`.
     */
    final public const WEBHOOK_SECRET = 'webhook_secret';

    final public const API_VERSION = 'api_version';

    private ?StripeClient $client = null;

    public function client(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        /** @var array<string, int|string|null> $config */
        $config = ['api_key' => $this->requireCredential(self::SECRET_KEY)];

        $version = $this->credential(self::API_VERSION);
        if ($version !== null) {
            // without a version stripe answers with the version the account is pinned to, which changes
            // over time - pinning it keeps the payloads the actions read stable
            $config['stripe_version'] = $version;
        }

        return $this->client = new StripeClient($config);
    }

    public function webhookSecret(): string
    {
        return $this->requireCredential(self::WEBHOOK_SECRET);
    }

    public function publishableKey(): string
    {
        return $this->credential(self::PUBLISHABLE_KEY) ?? '';
    }

    /**
     * Stripe wants the currency in lowercase, the platform stores it in uppercase.
     */
    public function currency(string $currency): string
    {
        return strtolower($currency);
    }

    /**
     * Asks stripe what happened to a payment, the payment intent first and the checkout session
     * behind it second.
     *
     * The intent is what the money of a stripe payment sits on, so it is asked for whenever it is
     * known - but a hosted checkout session only gets its intent when the payer confirmed it, so a
     * payment whose notification never arrived is more often found again through its session. A
     * resource stripe does not know answers with a 404, which is an answer of its own - `absent` -
     * instead of an error, because that is exactly what the reconciliation asked for.
     *
     * @throws \Stripe\Exception\ApiErrorException when stripe rejects the call for another reason
     */
    public function query(?string $sessionId, ?string $paymentIntentId): QueryResult
    {
        if ($paymentIntentId !== null && $paymentIntentId !== '') {
            return $this->queryIntent($paymentIntentId);
        }

        if ($sessionId !== null && $sessionId !== '') {
            return $this->querySession($sessionId);
        }

        // without a session id and without an intent id there is nothing stripe could be asked about
        return new QueryResult(QueryResult::STATUS_ABSENT);
    }

    private function queryIntent(string $paymentIntentId): QueryResult
    {
        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId);
        } catch (InvalidRequestException $exception) {
            if ($exception->getHttpStatus() === 404) {
                return new QueryResult(QueryResult::STATUS_ABSENT, raw: ['error' => $exception->getMessage()]);
            }

            throw $exception;
        }

        // what was captured is what the platform books: an intent may have collected less than it was
        // created for, e.g. when the payer tipped a different total
        $received = $intent->amount_received;

        $status = QueryResult::STATUS_PENDING;
        if ($intent->status === 'succeeded') {
            $status = QueryResult::STATUS_PAID;
        } elseif ($intent->status === 'canceled') {
            $status = QueryResult::STATUS_CLOSED;
        }

        return new QueryResult(
            status: $status,
            outPaymentNo: $intent->id,
            amount: \is_int($received) && $received > 0 ? $received : $intent->amount,
            currency: strtoupper((string) $intent->currency),
            channelStatus: $intent->status,
            raw: $intent->toArray(),
        );
    }

    private function querySession(string $sessionId): QueryResult
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (InvalidRequestException $exception) {
            if ($exception->getHttpStatus() === 404) {
                return new QueryResult(QueryResult::STATUS_ABSENT, raw: ['error' => $exception->getMessage()]);
            }

            throw $exception;
        }

        // a session is completed as soon as the payer is done, the money may still be on its way for
        // the payment methods stripe settles asynchronously
        $status = QueryResult::STATUS_PENDING;
        if ($session->status === 'complete' && $session->payment_status === 'paid') {
            $status = QueryResult::STATUS_PAID;
        } elseif ($session->status === 'expired') {
            $status = QueryResult::STATUS_CLOSED;
        }

        return new QueryResult(
            status: $status,
            outPaymentNo: $this->paymentIntentIdOf($session),
            amount: $session->amount_total,
            currency: strtoupper((string) $session->currency),
            channelStatus: $session->payment_status,
            raw: $session->toArray(),
        );
    }

    /**
     * The payment intent of a session, which is empty until the payer confirmed it.
     */
    private function paymentIntentIdOf(Session $session): ?string
    {
        $intent = $session->payment_intent;

        if ($intent instanceof PaymentIntent) {
            return $intent->id;
        }

        return \is_string($intent) && $intent !== '' ? $intent : null;
    }
}
