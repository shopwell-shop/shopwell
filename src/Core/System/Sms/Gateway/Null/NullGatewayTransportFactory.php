<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Null;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The baseline gateway. It always exists, so the component is usable and testable before any
 * provider was configured and a shop can switch sms on without sending a message.
 *
 * @internal
 */
#[Package('framework')]
final class NullGatewayTransportFactory implements SmsGatewayTransportFactoryInterface
{
    final public const TECHNICAL_NAME = 'null';

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function getLabelSnippet(): string
    {
        return 'sms.gateway.null';
    }

    public function getSupportedRegions(): array
    {
        return ['GLOBAL'];
    }

    public function supportsTemplateMode(): bool
    {
        return false;
    }

    public function getCredentialDefinitions(): array
    {
        return [];
    }

    public function create(
        array $credentials,
        array $options,
        ?EventDispatcherInterface $dispatcher = null,
    ): TransportInterface {
        return new NullSmsTransport($dispatcher);
    }
}
