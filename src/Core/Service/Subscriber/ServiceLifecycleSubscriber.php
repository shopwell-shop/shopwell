<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Event\NewServicesInstalledEvent;
use Shopwell\Core\Service\Notification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(private Notification $notification)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            NewServicesInstalledEvent::class => 'sendInstalledNotification',
        ];
    }

    public function sendInstalledNotification(NewServicesInstalledEvent $event): void
    {
        $this->notification->newServicesInstalled();
    }
}
