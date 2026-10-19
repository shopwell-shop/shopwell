<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Log;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\System\Sms\Util\PhoneNumberMaskerInterface;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The gateway that logs instead of sending, for development and for shops that only want to see
 * which messages would go out.
 *
 * @internal
 */
#[Package('framework')]
final class LogGatewayTransportFactory implements SmsGatewayTransportFactoryInterface
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

    public function getCredentialDefinitions(): array
    {
        return [];
    }

    public function create(
        array $credentials,
        array $options,
        ?EventDispatcherInterface $dispatcher = null,
    ): TransportInterface {
        return new LogSmsTransport($this->logger, $this->normalizer, $this->masker, $dispatcher);
    }
}
