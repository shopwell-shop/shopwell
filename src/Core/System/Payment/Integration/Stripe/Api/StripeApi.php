<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeWebhookVerifier;
use Shopwell\Core\System\Payment\Integration\Stripe\Request\StripePaymentStatus;
use Shopwell\Core\System\Payment\PaymentException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
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
     * @throws ApiErrorException when stripe rejects the call for another reason
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

    /**
     * Opens the payment at stripe as a payment intent, the flow the app approves itself with the
     * client side sdk. The answer carries the client secret the sdk approves with and the id the
     * platform asks for later, when the notification of the intent has to be matched.
     *
     * @param array<string, mixed> $payload the business content, wire snake_case
     *
     * @throws ApiErrorException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as
     *                              well and carries `error.code` and `error.message`
     */
    public function createPaymentIntent(array $payload): array
    {
        try {
            // the payload is built and validated by the action, the sdk shape is one more copy of it
            // @phpstan-ignore argument.type
            $answer = $this->client()->paymentIntents->create($payload);
        } catch (ApiErrorException $exception) {
            return self::rejectedAnswerOf($exception);
        }

        return $answer->toArray();
    }

    /**
     * Opens the payment at stripe as a hosted checkout session, the flow that needs no client side
     * sdk: the answer carries the url the payer is redirected to.
     *
     * The session is the only resource that can be asked for when the intent is still unknown - a
     * session gets its intent only once the payer confirmed it, which is exactly the case a
     * reconciliation has to catch.
     *
     * @param array<string, mixed> $payload the business content, wire snake_case
     *
     * @throws ApiErrorException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as
     *                              well and carries `error.code` and `error.message`
     */
    public function createCheckoutSession(array $payload): array
    {
        try {
            // the payload is built and validated by the action, the sdk shape is one more copy of it
            // @phpstan-ignore argument.type
            $answer = $this->client()->checkout->sessions->create($payload);
        } catch (ApiErrorException $exception) {
            return self::rejectedAnswerOf($exception);
        }

        return $answer->toArray();
    }

    /**
     * Refunds a part of a payment or the whole of it.
     *
     * The refund number of the platform is handed over as the idempotency key, so a repeated call
     * answers the very same refund instead of creating a second one - a retry after a timeout must
     * not refund twice.
     *
     * @param array<string, mixed> $payload the business content, wire snake_case
     *
     * @throws ApiErrorException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as
     *                              well and carries `error.code` and `error.message`
     */
    public function createRefund(array $payload, string $idempotencyKey): array
    {
        try {
            // the payload is built and validated by the action, the sdk shape is one more copy of it
            // @phpstan-ignore argument.type
            $answer = $this->client()->refunds->create($payload, ['idempotency_key' => $idempotencyKey]);
        } catch (ApiErrorException $exception) {
            return self::rejectedAnswerOf($exception);
        }

        return $answer->toArray();
    }

    /**
     * Proves a notification was sent by stripe: the raw body is signed with the signing secret of the
     * webhook endpoint, so an unsigned body confirms nothing.
     *
     * @param string $signature the value of the signature header, `t=...,v1=...`
     *
     * @throws PaymentException when the notification cannot be checked at all, e.g. without a secret
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        return StripeWebhookVerifier::verify($this->credentials(), $rawBody, $signature);
    }

    /**
     * The message of a rejection of the interface, null when the answer is no rejection.
     *
     * @param array<string, mixed> $answer
     */
    public static function rejectionMessageOf(array $answer): ?string
    {
        return self::rejectionFieldOf($answer, 'message');
    }

    /**
     * The code of a rejection of the interface, null when the answer carries none - the payout
     * actions map their failure to it.
     *
     * @param array<string, mixed> $answer
     */
    public static function rejectionCodeOf(array $answer): ?string
    {
        return self::rejectionFieldOf($answer, 'code');
    }

    /**
     * The payment intent of a session, which is empty until the payer confirmed it - both `null` and
     * the empty string mean the intent does not exist yet.
     *
     * @param array<string, mixed> $answer a checkout session of the interface
     */
    public static function paymentIntentIdOf(array $answer): ?string
    {
        $intent = $answer['payment_intent'] ?? null;

        if (\is_array($intent)) {
            $intent = $intent['id'] ?? null;
        }

        return \is_string($intent) && $intent !== '' ? $intent : null;
    }

    /**
     * Moves money from the platform balance into the balance of a connected account, the first of
     * the two stages a payout to a connected account consists of.
     *
     * A transfer has no status of its own - the answer only carries `reversed` and `amount_reversed`,
     * so a created transfer never means the recipient has the money yet, only that the platform
     * balance has paid it into the connected account.
     *
     * @param array<string, mixed> $payload the business content, wire snake_case
     *
     * @throws ApiErrorException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as
     *                              well and carries `error.code` and `error.message`
     */
    public function createTransfer(array $payload, string $idempotencyKey): array
    {
        try {
            // the payload is built and validated by the action, the sdk shape is one more copy of it
            // @phpstan-ignore argument.type
            $answer = $this->client()->transfers->create($payload, ['idempotency_key' => $idempotencyKey]);
        } catch (ApiErrorException $exception) {
            return self::rejectedAnswerOf($exception);
        }

        return $answer->toArray();
    }

    /**
     * Pays out of the balance of a connected account, the second of the two stages a payout to a
     * connected account consists of.
     *
     * The call rides the `Stripe-Account` header - it is made on behalf of the connected account and
     * draws from its balance, not from the platform one. It therefore must not name a `destination`:
     * with one stripe would pay out of the platform balance to the named account instead.
     *
     * @param array<string, mixed> $payload the business content, wire snake_case
     *
     * @throws ApiErrorException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as
     *                              well and carries `error.code` and `error.message`
     */
    public function createPayout(array $payload, string $accountId, string $idempotencyKey): array
    {
        try {
            // the payload is built and validated by the action, the sdk shape is one more copy of it
            // @phpstan-ignore argument.type
            $answer = $this->client()->payouts->create($payload, [
                'idempotency_key' => $idempotencyKey,
                'stripe_account' => $accountId,
            ]);
        } catch (ApiErrorException $exception) {
            return self::rejectedAnswerOf($exception);
        }

        return $answer->toArray();
    }

    /**
     * Asks stripe what happened to a payout of a connected account.
     *
     * Like the payout itself the query rides the `Stripe-Account` header - without it stripe would
     * look for the payout on the platform account and answer that it does not know it.
     *
     * @throws ApiErrorException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as
     *                              well and carries `error.code` and `error.message`
     */
    public function retrievePayout(string $payoutId, string $accountId): array
    {
        try {
            $answer = $this->client()->payouts->retrieve($payoutId, [], ['stripe_account' => $accountId]);
        } catch (ApiErrorException $exception) {
            return self::rejectedAnswerOf($exception);
        }

        return $answer->toArray();
    }

    private function queryIntent(string $paymentIntentId): QueryResult
    {
        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId);
        } catch (InvalidRequestException $exception) {
            if ($exception->getHttpStatus() === 404) {
                return QueryResult::absent($exception->getMessage());
            }

            throw $exception;
        }

        // what was captured is what the platform books: an intent may have collected less than it was
        // created for, e.g. when the payer tipped a different total
        $received = $intent->amount_received;

        return new QueryResult(
            status: StripePaymentStatus::intent((string) $intent->status),
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
                return QueryResult::absent($exception->getMessage());
            }

            throw $exception;
        }

        $answer = $session->toArray();

        return new QueryResult(
            status: StripePaymentStatus::session((string) $session->status, (string) $session->payment_status),
            outPaymentNo: self::paymentIntentIdOf($answer),
            amount: $session->amount_total,
            currency: strtoupper((string) $session->currency),
            channelStatus: $session->payment_status,
            raw: $answer,
        );
    }

    /**
     * One field of the error of a rejected call; an answer that is no rejection carries none.
     *
     * @param array<string, mixed> $answer
     */
    private static function rejectionFieldOf(array $answer, string $field): ?string
    {
        $error = $answer['error'] ?? null;

        if (!\is_array($error)) {
            return null;
        }

        $value = $error[$field] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The answer of a rejection of the interface, normalized the way the actions read it.
     *
     * A rejection with a body stripe parsed is an answer of its own: its `error.code` and
     * `error.message` are what the actions map the failure with, and the original body rides along
     * under `raw` - when the interface behaves differently than the codes promise, the evidence is
     * the raw message, not the two keys it was narrowed to. A failure without a parsable body - a
     * network failure, an html error page - is no answer at all and bubbles up.
     *
     * @throws ApiErrorException when the answer carries no parsable body
     *
     * @return array<string, mixed>
     */
    private static function rejectedAnswerOf(ApiErrorException $exception): array
    {
        $decoded = $exception->getJsonBody();

        if (!\is_array($decoded)) {
            throw $exception;
        }

        $error = \is_array($decoded['error'] ?? null) ? $decoded['error'] : [];

        return [
            'error' => [
                'code' => \is_string($error['code'] ?? null) ? $error['code'] : null,
                'message' => \is_string($error['message'] ?? null) ? $error['message'] : null,
            ],
            // the original body rides along - the evidence of a support case
            'raw' => $decoded,
        ];
    }
}
