<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Integration\Null;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final class NullSmsProvider extends AbstractSmsProvider
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

    public function createTransport(
        array $credentials,
        array $options,
        ?EventDispatcherInterface $dispatcher = null,
    ): TransportInterface {
        return new NullSmsTransport($dispatcher);
    }
}
