<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Service;

use Payum\Core\GatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Struct\ResolvedPaymentConfig;

/**
 * Builds the payum gateway of one payment.
 *
 * The gateways are not statically configured: the credentials belong to the app and the payment
 * interface, the parameters belong to the channel, so the gateway is assembled per payment through the
 * gateway factory of the interface. payum is used as the orchestration layer (requests and actions),
 * the payment order of this platform stays the source of truth, therefore no payum token storage is
 * involved.
 *
 * The configuration keys are the ones {@see AbstractPaymentApi} reads - one factory per interface
 * serves every channel of it, so the channel travels in the configuration as well.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentGatewayFactory
{
    public function create(
        ResolvedPaymentConfig $resolved,
        string $notifyUrl,
        string $returnUrl
    ): GatewayInterface {
        return $resolved->provider->gatewayFactory->create([
            'payum.factory_name' => $resolved->provider->technicalName,
            'payum.factory_title' => $resolved->provider->label,
            AbstractPaymentApi::CONFIG_PROVIDER => $resolved->provider->technicalName,
            AbstractPaymentApi::CONFIG_CHANNEL => $resolved->channel->technicalName,
            AbstractPaymentApi::CONFIG_MODE => $resolved->mode,
            AbstractPaymentApi::CONFIG_CREDENTIALS => $resolved->credentials,
            AbstractPaymentApi::CONFIG_PARAMS => $resolved->params,
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => $resolved->runtimeParams,
            AbstractPaymentApi::CONFIG_SETTINGS => $resolved->settings,
            AbstractPaymentApi::CONFIG_NOTIFY_URL => $notifyUrl,
            AbstractPaymentApi::CONFIG_RETURN_URL => $returnUrl,
        ]);
    }
}
