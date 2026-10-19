<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Gateway;
use Payum\Core\GatewayFactoryInterface;
use Payum\Core\GatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * The payum gateway factory of one payment interface: the container tells it which api and which
 * actions the interface consists of, and it assembles the gateway of a payment out of them.
 *
 * The gateways are not statically configured: the credentials belong to the app and the payment
 * interface, the parameters belong to the channel, so the gateway is assembled per payment through
 * {@see createForPayment()} - payum is used as the orchestration layer (requests and actions), the
 * payment order of this platform stays the source of truth, therefore no payum token storage is
 * involved. One factory serves every channel of an interface, so the channel travels in the resolved
 * configuration of the payment.
 *
 * @internal
 */
#[Package('framework')]
class PaymentGatewayFactory implements GatewayFactoryInterface
{
    final public const CONFIG_RESOLVED = 'payment.resolved';

    final public const CONFIG_NOTIFY_URL = 'payment.notify_url';

    final public const CONFIG_RETURN_URL = 'payment.return_url';

    /**
     * @param class-string<AbstractPaymentApi> $apiClass the api of the interface, built per payment
     * @param list<ActionInterface> $actions the actions of the interface, shared services without state
     * @param list<mixed> $apiArguments the services the api needs besides the payment
     */
    public function __construct(
        private readonly string $provider,
        private readonly string $apiClass,
        private readonly array $actions,
        private readonly array $apiArguments = [],
    ) {
    }

    /**
     * Assembles the gateway of one payment out of its resolved configuration.
     */
    public function createForPayment(ResolvedPaymentConfig $resolved, string $notifyUrl, string $returnUrl): GatewayInterface
    {
        return $this->create([
            'payum.factory_name' => $this->provider,
            'payum.factory_title' => $resolved->provider->label,
            self::CONFIG_RESOLVED => $resolved,
            self::CONFIG_NOTIFY_URL => $notifyUrl,
            self::CONFIG_RETURN_URL => $returnUrl,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config = []): GatewayInterface
    {
        $config = $this->createConfig($config);

        $gateway = new Gateway();
        $gateway->addApi($this->newApi($config));

        foreach ($this->actions as $action) {
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
            'payum.factory_name' => $this->provider,
            'payum.factory_title' => $this->provider,
            self::CONFIG_RESOLVED => null,
            self::CONFIG_NOTIFY_URL => '',
            self::CONFIG_RETURN_URL => '',
        ], $config);
    }

    /**
     * The technical name of the payment interface, e.g. `alipay`.
     */
    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function newApi(array $config): AbstractPaymentApi
    {
        $resolved = $config[self::CONFIG_RESOLVED];
        \assert($resolved instanceof ResolvedPaymentConfig);

        $apiClass = $this->apiClass;

        return new $apiClass(
            $resolved,
            (string) $config[self::CONFIG_NOTIFY_URL],
            (string) $config[self::CONFIG_RETURN_URL],
            ...$this->apiArguments
        );
    }
}
