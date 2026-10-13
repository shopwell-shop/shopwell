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
            && $request->getModel() instanceof \ArrayObject;
    }

    /**
     * @throws RequestNotSupportedException
     *
     * @return \ArrayObject<string, mixed>
     */
    protected function modelOf(mixed $request): \ArrayObject
    {
        if (!$request instanceof Generic || !$request->getModel() instanceof \ArrayObject) {
            throw RequestNotSupportedException::create($request);
        }

        return $request->getModel();
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    protected function value(\ArrayObject $model, string $key): mixed
    {
        return $model->offsetExists($key) ? $model->offsetGet($key) : null;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    protected function stringValue(\ArrayObject $model, string $key, string $default = ''): string
    {
        $value = $this->value($model, $key);

        return \is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    protected function intValue(\ArrayObject $model, string $key, int $default = 0): int
    {
        $value = $this->value($model, $key);

        return \is_int($value) ? $value : $default;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    protected function boolValue(\ArrayObject $model, string $key, bool $default = false): bool
    {
        $value = $this->value($model, $key);

        return \is_bool($value) ? $value : $default;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     * @param array<string, mixed> $payload
     */
    protected function recordRequest(\ArrayObject $model, array $payload): void
    {
        PaymentGatewayModel::recordRequest($model, $payload);
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     * @param array<string, mixed> $payload
     */
    protected function recordResponse(\ArrayObject $model, array $payload): void
    {
        PaymentGatewayModel::recordResponse($model, $payload);
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    protected function setResult(\ArrayObject $model, object $result): void
    {
        $model->offsetSet(PaymentGatewayModel::RESULT, $result);
    }
}
