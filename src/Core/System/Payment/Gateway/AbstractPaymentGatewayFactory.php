<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Gateway;
use Payum\Core\GatewayFactoryInterface;
use Payum\Core\GatewayInterface;
use Shopwell\Core\Framework\Log\Package;

/**
 * Base of the gateway factories of the payment interfaces.
 *
 * payum does not build its gateways through the container here: the credentials belong to an app and an
 * interface, so a gateway is assembled per payment by the factory of that interface. The factory
 * therefore only has to answer which api object and which actions the interface consists of - and there
 * is one factory per interface, not per channel: the channels of an interface share everything but the
 * call that opens a payment, so the capture action dispatches on the channel itself.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractPaymentGatewayFactory implements GatewayFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config = []): GatewayInterface
    {
        $config = $this->createConfig($config);

        $gateway = new Gateway();
        $gateway->addApi($this->createApi($config));

        foreach ($this->createActions() as $action) {
            $gateway->addAction($action);
        }

        return $gateway;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function createConfig(array $config = []): array
    {
        return array_replace([
            'payum.factory_name' => $this->provider(),
            'payum.factory_title' => $this->provider(),
            AbstractPaymentApi::CONFIG_PROVIDER => $this->provider(),
            AbstractPaymentApi::CONFIG_CHANNEL => '',
            AbstractPaymentApi::CONFIG_MODE => '',
            AbstractPaymentApi::CONFIG_CREDENTIALS => [],
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => '',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ], $config);
    }

    /**
     * The technical name of the payment interface, e.g. `alipay`.
     */
    abstract public function provider(): string;

    /**
     * @param array<string, mixed> $config
     */
    abstract protected function createApi(array $config): PaymentApiInterface;

    /**
     * @return list<ActionInterface>
     */
    abstract protected function createActions(): array;
}
