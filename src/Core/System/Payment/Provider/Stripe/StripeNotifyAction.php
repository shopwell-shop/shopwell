<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;

/**
 * Verifies and reads the asynchronous notification of stripe.
 *
 * Stripe signs the raw body with the signing secret of the webhook endpoint; the payload itself is not
 * encrypted, which is what lets the platform match a notification to its app before it is verified.
 *
 * A merchant subscribes the notification url to the payment and the refund events at stripe; everything
 * else is acknowledged but does not confirm anything.
 *
 * @internal
 */
#[Package('framework')]
final class StripeNotifyAction extends AbstractPaymentAction
{
    final public const EVENT_PAYMENT_INTENT_SUCCEEDED = 'payment_intent.succeeded';
    final public const EVENT_CHECKOUT_SESSION_COMPLETED = 'checkout.session.completed';

    /**
     * The events that report a payment that will not be completed.
     */
    final public const PAYMENT_FAILED_EVENTS = [
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'checkout.session.expired',
        'checkout.session.async_payment_failed',
    ];

    /**
     * The events that report a refund.
     */
    final public const REFUND_EVENTS = ['refund.created', 'refund.updated', 'refund.failed'];

    protected string $apiClass = StripeApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $rawBody = $this->stringValue($model, PaymentGatewayModel::RAW_BODY);

        $signatureValid = $this->verify($api, $rawBody, $this->signatureOf($model));

        $event = json_decode($rawBody, true);
        $event = \is_array($event) ? $event : [];

        $object = \is_array($event['data'] ?? null) && \is_array($event['data']['object'] ?? null)
            ? $event['data']['object']
            : [];

        $type = \is_string($event['type'] ?? null) ? $event['type'] : '';

        $this->recordResponse($model, $object);

        $isRefund = \in_array($type, self::REFUND_EVENTS, true);

        $this->setResult($model, new NotificationResult(
            signatureValid: $signatureValid,
            succeeded: $signatureValid && $this->succeeded($type, $object),
            outPaymentNo: $isRefund ? null : $this->outPaymentNoOf($type, $object),
            outRefundId: $isRefund ? $this->stringOf($object, 'id') : null,
            amount: $this->amountOf($type, $object),
            currency: strtoupper($this->currencyOf($object)),
            status: $type,
            raw: $object,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Notify::class);
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }

    /**
     * Only stripe may tell us whether a payment happened, so an unsigned body confirms nothing.
     */
    private function verify(StripeApi $api, string $rawBody, string $signature): bool
    {
        try {
            WebhookSignature::verifyHeader($rawBody, $signature, $api->webhookSecret());

            return true;
        } catch (SignatureVerificationException) {
            return false;
        } catch (\Throwable $exception) {
            throw PaymentException::notificationInvalid($api->channel(), $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    private function succeeded(string $type, array $object): bool
    {
        if (\in_array($type, self::PAYMENT_FAILED_EVENTS, true)) {
            return false;
        }

        if ($type === self::EVENT_PAYMENT_INTENT_SUCCEEDED) {
            return ($object['status'] ?? null) === 'succeeded';
        }

        if ($type === self::EVENT_CHECKOUT_SESSION_COMPLETED) {
            // a session is completed as soon as the payer is done, the money may still be on its way
            // for the payment methods stripe settles asynchronously
            return ($object['payment_status'] ?? null) === 'paid';
        }

        if (\in_array($type, self::REFUND_EVENTS, true)) {
            return ($object['status'] ?? null) === StripeRefundAction::STATUS_SUCCEEDED;
        }

        return false;
    }

    /**
     * The id stripe gave the payment, which is the payment intent - that is what a refund works on.
     *
     * @param array<string, mixed> $object
     */
    private function outPaymentNoOf(string $type, array $object): ?string
    {
        if ($type === self::EVENT_CHECKOUT_SESSION_COMPLETED) {
            // the intent of a session is handed over as a string, and only as an object once it is
            // expanded
            $intent = $object['payment_intent'] ?? null;

            if (\is_array($intent)) {
                return $this->stringOf($intent, 'id');
            }

            return \is_string($intent) && $intent !== '' ? $intent : null;
        }

        return $this->stringOf($object, 'id');
    }

    /**
     * @param array<string, mixed> $object
     */
    private function amountOf(string $type, array $object): ?int
    {
        // what was captured is what the platform books: an intent may have collected less than it was
        // created for, e.g. when the payer tipped a different total
        if ($type === self::EVENT_PAYMENT_INTENT_SUCCEEDED) {
            $received = $this->intOf($object, 'amount_received');

            if ($received !== null && $received > 0) {
                return $received;
            }
        }

        if ($type === self::EVENT_CHECKOUT_SESSION_COMPLETED) {
            $total = $this->intOf($object, 'amount_total');

            if ($total !== null) {
                return $total;
            }
        }

        return $this->intOf($object, 'amount');
    }

    /**
     * @param array<string, mixed> $object
     */
    private function currencyOf(array $object): string
    {
        $currency = $object['currency'] ?? null;

        return \is_string($currency) && $currency !== '' ? $currency : 'USD';
    }

    /**
     * @param array<string, mixed> $object
     */
    private function stringOf(array $object, string $key): ?string
    {
        $value = $object[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $object
     */
    private function intOf(array $object, string $key): ?int
    {
        $value = $object[$key] ?? null;

        return \is_int($value) ? $value : (\is_numeric($value) ? (int) $value : null);
    }

    /**
     * The signature header stripe signs the body with, `t=...,v1=...`.
     *
     * @param \ArrayObject<string, mixed> $model
     *
     * @throws PaymentException
     */
    private function signatureOf(\ArrayObject $model): string
    {
        $headers = $model->offsetExists(PaymentGatewayModel::HEADERS) ? $model->offsetGet(PaymentGatewayModel::HEADERS) : null;

        if (\is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (!\is_string($name) || strtolower(str_replace('_', '-', $name)) !== StripeNotificationExtractor::HEADER_SIGNATURE) {
                    continue;
                }

                if (\is_array($value)) {
                    $value = reset($value);
                }

                if (\is_scalar($value)) {
                    return (string) $value;
                }
            }
        }

        throw PaymentException::notificationInvalid(
            StripeGatewayFactory::PROVIDER,
            'the notification carries no stripe signature'
        );
    }
}
