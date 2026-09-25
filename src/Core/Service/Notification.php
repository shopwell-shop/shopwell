<?php declare(strict_types=1);

namespace Shopwell\Core\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\NotificationService;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Notification
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function newServicesInstalled(): void
    {
        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'positive',
                'message' => 'New services have been installed. Reload your administration to see what\'s new.',
                'adminOnly' => true,
                'requiredPrivileges' => ['system.plugin_maintain'],
            ],
            Context::createDefaultContext()
        );
    }
}
