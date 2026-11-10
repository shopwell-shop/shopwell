<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Integration\Log;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Util\PhoneNumberMaskerInterface;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final class LogSmsProvider extends AbstractSmsProvider
{
    final public const TECHNICAL_NAME = 'log';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PhoneNumberNormalizerInterface $normalizer,
        private readonly PhoneNumberMaskerInterface $masker,
    ) {
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function getLabelSnippet(): string
    {
        return 'sms.gateway.log';
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
        return new LogSmsTransport($this->logger, $this->normalizer, $this->masker, $dispatcher);
    }
}
