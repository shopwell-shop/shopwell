<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Notification;

use Shopwell\Core\Framework\App\Event\AppActivatedEvent;
use Shopwell\Core\Framework\App\Event\AppChangedEvent;
use Shopwell\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopwell\Core\Framework\App\Event\AppDeletedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
class AppMcpCapabilityLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AppMcpCapabilityDetector $capabilityDetector,
        private readonly McpListChangedNotifier $notifier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppActivatedEvent::class => 'onAppChanged',
            AppDeactivatedEvent::class => 'onAppChanged',
            AppDeletedEvent::class => 'onAppDeleted',
        ];
    }

    public function onAppChanged(AppChangedEvent $event): void
    {
        $this->notifyForApp($event->getApp()->getId());
    }

    public function onAppDeleted(AppDeletedEvent $event): void
    {
        $this->notifyForApp($event->getAppId());
    }

    private function notifyForApp(string $appId): void
    {
        $this->notifier->notify($this->capabilityDetector->persistedForApp($appId));
    }
}
