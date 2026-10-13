<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\Money;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Verifies and reads the asynchronous notification of paypal.
 *
 * Paypal posts a plain json body and signs the transmission with a certificate it publishes: the
 * signature is verified first, only then is the event looked at. Everything the platform needs to
 * match the notification to its payment is inside the body - the payment number as the custom id and
 * the trade number of the app as the invoice id - so a paypal merchant account may well use one single
 * webhook url for all payments.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalNotifyAction extends AbstractPaymentAction
{
    /**
     * The events that confirm a capture.
     */
    final public const CAPTURE_SUCCEEDED_EVENTS = ['PAYMENT.CAPTURE.COMPLETED'];

    /**
     * The events that report a capture that will never happen.
     */
    final public const CAPTURE_FAILED_EVENTS = ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED'];

    /**
     * The events that confirm a refund.
     */
    final public const REFUND_SUCCEEDED_EVENTS = ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED'];

    protected string $apiClass = PaypalApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $rawBody = $this->stringValue($model, PaymentGatewayModel::RAW_BODY);
        $signatureValid = $api->verifyWebhookSignature($rawBody, $this->headersOf($model));

        $event = $this->decode($rawBody);
        $resource = \is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $eventType = \is_string($event['event_type'] ?? null) ? $event['event_type'] : '';

        $this->recordResponse($model, $event);

        $refund = $this->isRefundEvent($eventType);

        $this->setResult($model, new NotificationResult(
            signatureValid: $signatureValid,
            succeeded: $this->succeeded($eventType),
            outPaymentNo: $this->stringOf($resource, 'id'),
            outRefundId: $refund ? $this->stringOf($resource, 'id') : null,
            amount: $this->amountOf($resource),
            currency: strtoupper($this->currencyOf($resource)),
            status: $eventType,
            raw: $resource,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Notify::class);
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }

    /**
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    private function decode(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);

        if (!\is_array($decoded)) {
            throw PaymentException::notificationInvalid(PaypalGatewayFactory::PROVIDER, 'the notification is not a json object');
        }

        return $decoded;
    }

    private function isRefundEvent(string $eventType): bool
    {
        return \in_array($eventType, self::REFUND_SUCCEEDED_EVENTS, true)
            || str_contains($eventType, 'REFUND');
    }

    private function succeeded(string $eventType): bool
    {
        return \in_array($eventType, [...self::CAPTURE_SUCCEEDED_EVENTS, ...self::REFUND_SUCCEEDED_EVENTS], true);
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function amountOf(array $resource): ?int
    {
        $amount = \is_array($resource['amount'] ?? null) ? $resource['amount'] : $resource;
        $value = $amount['value'] ?? null;

        if (!\is_scalar($value) || (string) $value === '') {
            return null;
        }

        try {
            return Money::toMinor((string) $value, strtoupper($this->currencyOf($resource)));
        } catch (PaymentException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function currencyOf(array $resource): string
    {
        $amount = \is_array($resource['amount'] ?? null) ? $resource['amount'] : [];
        $currency = $amount['currency_code'] ?? null;

        return \is_scalar($currency) && (string) $currency !== '' ? (string) $currency : 'USD';
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function stringOf(array $resource, string $key): ?string
    {
        $value = $resource[$key] ?? null;

        return \is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     *
     * @return array<string, string>
     */
    private function headersOf(\ArrayObject $model): array
    {
        $headers = $model->offsetExists(PaymentGatewayModel::HEADERS) ? $model->offsetGet(PaymentGatewayModel::HEADERS) : null;

        if (!\is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            if (\is_array($value)) {
                $value = reset($value);
            }

            if (\is_scalar($value)) {
                $normalized[strtolower((string) $name)] = (string) $value;
            }
        }

        return $normalized;
    }
}
