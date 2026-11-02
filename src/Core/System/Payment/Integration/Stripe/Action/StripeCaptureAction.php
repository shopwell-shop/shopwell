<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PayResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Symfony\Component\Clock\NativeClock;

/**
 * Opens a payment at stripe and answers the app with what it needs to hand the payer over.
 *
 * Two flows are supported, selected by the setting `flow`:
 *
 *  - `redirect` (the default) creates a hosted checkout session: the app redirects the payer to the url
 *    of the session, which needs no client side sdk at all.
 *  - `sdk` creates a payment intent: the app approves it with the stripe javascript sdk or the mobile
 *    sdk of the platform, using the client secret that is handed out here.
 *
 * Both flows carry the payment number of the platform in the metadata of the payment intent, which is
 * the only thing that survives into the notification of stripe.
 *
 * @internal
 */
#[Package('framework')]
final class StripeCaptureAction extends AbstractPaymentAction
{
    /**
     * The setting that selects how the payer is handed over: `redirect` or `sdk`.
     */
    final public const SETTING_FLOW = 'flow';

    final public const FLOW_REDIRECT = 'redirect';
    final public const FLOW_SDK = 'sdk';

    /**
     * A stripe checkout session may not expire earlier than half an hour after it was created.
     */
    private const MIN_SESSION_LIFETIME = 1800;

    /**
     * Stripe cuts the description of a payment intent at 1000 characters.
     */
    private const MAX_DESCRIPTION_LENGTH = 1000;

    protected string $apiClass = StripeApi::class;

    public function __construct(private readonly ClockInterface $clock = new NativeClock())
    {
    }

    public function capability(): PaymentCapability
    {
        return PaymentCapability::CAPTURE;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $currency = $api->currency($this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'));
        $amount = $this->intValue($model, PaymentGatewayModel::AMOUNT);
        $metadata = $this->metadataOf($model);

        $flow = strtolower(trim($api->setting(self::SETTING_FLOW, self::FLOW_REDIRECT) ?? self::FLOW_REDIRECT));

        $this->recordRequest($model, [
            'flow' => $flow,
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
        ]);

        $params = $flow === self::FLOW_SDK
            ? $this->captureWithPaymentIntent($api, $model, $amount, $currency, $metadata)
            : $this->captureWithCheckoutSession($api, $model, $amount, $currency, $metadata);

        $this->setResult($model, new PayResult($params));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Capture::class);
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }

    /**
     * The client side flow: the app approves the payment intent itself.
     *
     * @param array<string, string> $metadata
     */
    private function captureWithPaymentIntent(
        StripeApi $api,
        PaymentGatewayModel $model,
        int $amount,
        string $currency,
        array $metadata
    ): PayParams {
        /** @var array<string, mixed> $payload */
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ];

        $description = mb_substr($this->stringValue($model, PaymentGatewayModel::SUBJECT), 0, self::MAX_DESCRIPTION_LENGTH);
        if ($description !== '') {
            $payload['description'] = $description;
        }

        try {
            $answer = $api->createPaymentIntent($payload);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (isset($answer['error'])) {
            $this->recordResponse($model, $answer);

            throw PaymentException::gatewayError(
                $api->channel(),
                StripeApi::rejectionMessageOf($answer) ?? 'the channel rejected the payment'
            );
        }

        $this->recordResponse($model, [
            'id' => $answer['id'] ?? null,
            'status' => $answer['status'] ?? null,
        ]);

        $clientSecret = $answer['client_secret'] ?? null;

        if (!\is_string($clientSecret) || $clientSecret === '') {
            throw PaymentException::gatewayError($api->channel(), 'the channel did not answer a client secret');
        }

        return new PayParams(
            flow: PaymentFlow::SDK,
            extra: [
                'payment_intent_id' => (string) ($answer['id'] ?? ''),
                'client_secret' => $clientSecret,
                'publishable_key' => $api->publishableKey(),
            ]
        );
    }

    /**
     * The redirect flow: stripe hosts the payment page.
     *
     * @param array<string, string> $metadata
     */
    private function captureWithCheckoutSession(
        StripeApi $api,
        PaymentGatewayModel $model,
        int $amount,
        string $currency,
        array $metadata
    ): PayParams {
        $returnUrl = $api->returnUrl();
        /** @var array<string, mixed> $payload */
        $payload = [
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => mb_substr($this->stringValue($model, PaymentGatewayModel::SUBJECT), 0, 255),
                    ],
                ],
            ]],
            'client_reference_id' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            // the metadata of the session is only visible on the session, the payment intent needs its
            // own copy - it is the payment intent that the notification carries
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
        ];

        if ($returnUrl !== '') {
            $payload['success_url'] = $returnUrl;
            $payload['cancel_url'] = $returnUrl;
        }

        $expireAt = $this->value($model, PaymentGatewayModel::EXPIRE_AT);
        if ($expireAt instanceof \DateTimeInterface && $expireAt->getTimestamp() >= $this->clock->now()->getTimestamp() + self::MIN_SESSION_LIFETIME) {
            $payload['expires_at'] = $expireAt->getTimestamp();
        }

        try {
            $answer = $api->createCheckoutSession($payload);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        if (isset($answer['error'])) {
            $this->recordResponse($model, $answer);

            throw PaymentException::gatewayError(
                $api->channel(),
                StripeApi::rejectionMessageOf($answer) ?? 'the channel rejected the payment'
            );
        }

        $paymentIntentId = StripeApi::paymentIntentIdOf($answer);

        $this->recordResponse($model, [
            'id' => $answer['id'] ?? null,
            'payment_status' => $answer['payment_status'] ?? null,
            'payment_intent' => $paymentIntentId,
        ]);

        $payUrl = $answer['url'] ?? null;

        if (!\is_string($payUrl) || $payUrl === '') {
            throw PaymentException::gatewayError($api->channel(), 'the channel did not answer a checkout url');
        }

        return new PayParams(
            flow: PaymentFlow::REDIRECT,
            payUrl: $payUrl,
            httpMethod: 'GET',
            extra: [
                'session_id' => (string) ($answer['id'] ?? ''),
                'payment_intent_id' => $paymentIntentId,
            ]
        );
    }

    /**
     * Everything the platform needs to match a notification of stripe to its payment. Stripe only
     * accepts strings, so both numbers are handed over as such.
     *
     * @return array<string, string>
     */
    private function metadataOf(PaymentGatewayModel $model): array
    {
        $metadata = [
            StripeNotificationExtractor::METADATA_PAYMENT_NO => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
        ];

        $outTradeNo = $this->stringValue($model, PaymentGatewayModel::OUT_TRADE_NO);
        if ($outTradeNo !== '') {
            $metadata[StripeNotificationExtractor::METADATA_OUT_TRADE_NO] = $outTradeNo;
        }

        return $metadata;
    }
}
