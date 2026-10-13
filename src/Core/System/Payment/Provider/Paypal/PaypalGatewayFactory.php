<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Payum\Core\Action\ActionInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentApiInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The payum gateway factory of paypal.
 *
 * The http client is needed by the api to fetch the certificate a webhook of paypal is signed with,
 * which is the only reason the factory is not stateless.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalGatewayFactory extends AbstractPaymentGatewayFactory
{
    final public const PROVIDER = 'paypal';

    public function __construct(private readonly ?HttpClientInterface $httpClient = null)
    {
    }

    public function provider(): string
    {
        return self::PROVIDER;
    }

    protected function createApi(array $config): PaymentApiInterface
    {
        return new PaypalApi($config, $this->httpClient);
    }

    /**
     * @return list<ActionInterface>
     */
    protected function createActions(): array
    {
        return [
            new PaypalCaptureAction(),
            new PaypalRefundAction(),
            new PaypalNotifyAction(),
        ];
    }
}
