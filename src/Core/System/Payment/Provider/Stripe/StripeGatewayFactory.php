<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Payum\Core\Action\ActionInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentApiInterface;

/**
 * The payum gateway factory of stripe.
 *
 * @internal
 */
#[Package('framework')]
final class StripeGatewayFactory extends AbstractPaymentGatewayFactory
{
    final public const PROVIDER = 'stripe';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    protected function createApi(array $config): PaymentApiInterface
    {
        return new StripeApi($config);
    }

    /**
     * @return list<ActionInterface>
     */
    protected function createActions(): array
    {
        return [
            new StripeCaptureAction(),
            new StripeRefundAction(),
            new StripeNotifyAction(),
        ];
    }
}
