<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Generic;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Base of the payum actions of the channels.
 *
 * The actions read the model of the request, talk to the channel and finally store a typed result in
 * the model. Everything they send and everything they receive is recorded on the model as well, so the
 * service that called the gateway can persist a complete transaction - also when the action throws.
 *
 * Every action names the api it works with in `$apiClass` - payum validates what it injects against
 * it - and overrides `api()` with a covariant return type, so it can call the methods of its concrete
 * api without a cast.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractPaymentAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * The api class the action works with, validated when payum injects the api.
     */
    protected string $apiClass = AbstractPaymentApi::class;

    private ?AbstractPaymentApi $api = null;

    public function setApi($api): void
    {
        if (!$api instanceof $this->apiClass) {
            throw PaymentException::unsupportedApi(static::class, \is_object($api) ? $api::class : \gettype($api));
        }

        // a declaration may name a class the api does not extend, so the guarantee of the base class
        // is re-checked instead of assumed
        if (!$api instanceof AbstractPaymentApi) {
            throw PaymentException::unsupportedApi(static::class, $api::class);
        }

        $this->api = $api;
    }

    protected function api(): AbstractPaymentApi
    {
        if (!$this->api instanceof AbstractPaymentApi) {
            throw PaymentException::actionWithoutApi(static::class);
        }

        return $this->api;
    }

    protected function isSupported(mixed $request, string $requestClass): bool
    {
        return $request instanceof $requestClass
            && $request instanceof Generic
            && $request->getModel() instanceof PaymentGatewayModel;
    }

    /**
     * @throws RequestNotSupportedException
     */
    protected function modelOf(mixed $request): PaymentGatewayModel
    {
        if (!$request instanceof Generic || !$request->getModel() instanceof PaymentGatewayModel) {
            throw RequestNotSupportedException::create($request);
        }

        return $request->getModel();
    }

    protected function value(PaymentGatewayModel $model, string $key): mixed
    {
        return $model->offsetExists($key) ? $model->offsetGet($key) : null;
    }

    protected function stringValue(PaymentGatewayModel $model, string $key, string $default = ''): string
    {
        $value = $this->value($model, $key);

        return \is_scalar($value) ? (string) $value : $default;
    }

    protected function intValue(PaymentGatewayModel $model, string $key, int $default = 0): int
    {
        $value = $this->value($model, $key);

        return \is_int($value) ? $value : $default;
    }

    protected function boolValue(PaymentGatewayModel $model, string $key, bool $default = false): bool
    {
        $value = $this->value($model, $key);

        return \is_bool($value) ? $value : $default;
    }

    /**
     * The headers the channel sent with its notification, as `lower case name => first value`.
     *
     * Http header names are case insensitive and a channel may send a header more than once, so the
     * names are folded to lower case and only the first value of a repeated header is kept - which is
     * the one every interface signs with.
     *
     * @return array<string, string>
     */
    protected function headersOf(PaymentGatewayModel $model): array
    {
        $headers = $this->value($model, PaymentGatewayModel::HEADERS);

        if (!\is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (\is_array($value)) {
                $value = reset($value);
            }

            if (\is_scalar($value)) {
                $normalized[strtolower(str_replace('_', '-', (string) $name))] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * One header of the notification, or null when the channel did not send it.
     */
    protected function headerOf(PaymentGatewayModel $model, string $name): ?string
    {
        return $this->headersOf($model)[strtolower(str_replace('_', '-', $name))] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function recordRequest(PaymentGatewayModel $model, array $payload): void
    {
        $model->recordRequest($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function recordResponse(PaymentGatewayModel $model, array $payload): void
    {
        $model->recordResponse($payload);
    }

    protected function setResult(PaymentGatewayModel $model, object $result): void
    {
        $model->offsetSet(PaymentGatewayModel::RESULT, $result);
    }
}
