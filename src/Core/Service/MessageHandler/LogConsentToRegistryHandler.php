<?php declare(strict_types=1);

namespace Shopwell\Core\Service\MessageHandler;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Message\LogPermissionToRegistryMessage;
use Shopwell\Core\Service\ServiceRegistry\PermissionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final class LogConsentToRegistryHandler
{
    public function __construct(private readonly PermissionLogger $logger)
    {
    }

    public function __invoke(LogPermissionToRegistryMessage $message): void
    {
        $this->logger->logSync($message->permissionsConsent, $message->consentState);
    }
}
