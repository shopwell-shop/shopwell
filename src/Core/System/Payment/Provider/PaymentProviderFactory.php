<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Payum\Core\GatewayFactoryInterface;
use Shopwell\Core\Framework\Log\Package;

/**
 * Builds a payment interface from its declaration and the php services that talk to it.
 *
 * The split is the point: the declaration file owns the shape of the configuration - which is what the
 * administration renders - while the container owns the code that talks to the interface. An interface
 * of an extension therefore only has to bring its own xml next to its own api class.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderFactory
{
    public function __construct(private readonly PaymentProviderSchemaReader $schemaReader)
    {
    }

    public function create(
        string $definitionFile,
        GatewayFactoryInterface $gatewayFactory,
        PaymentNotificationExtractorInterface $notificationExtractor
    ): PaymentProvider {
        return PaymentProvider::fromDefinition(
            $this->schemaReader->readProvider($definitionFile),
            $gatewayFactory,
            $notificationExtractor
        );
    }
}
