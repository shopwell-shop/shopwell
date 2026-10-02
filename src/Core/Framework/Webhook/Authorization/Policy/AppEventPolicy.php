<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Authorization\Policy;

use Shopwell\Core\Framework\App\Event\AppActivatedEvent;
use Shopwell\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopwell\Core\Framework\App\Event\AppDeletedEvent;
use Shopwell\Core\Framework\App\Event\AppInstalledEvent;
use Shopwell\Core\Framework\App\Event\AppLifecycleEvent;
use Shopwell\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopwell\Core\Framework\App\Event\AppUpdatedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopwell\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopwell\Core\Framework\Webhook\Hookable;
use Shopwell\Core\Framework\Webhook\Webhook;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedHook;

/**
 * Only apps can subscribe to and receive app events such as app.installed or app.config.changed.
 * An inactive app only receives events that implement {@see AppLifecycleEvent}.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class AppEventPolicy implements Policy
{
    private const APP_EVENTS = [
        AppActivatedEvent::NAME,
        AppDeactivatedEvent::NAME,
        AppDeletedEvent::NAME,
        AppInstalledEvent::NAME,
        AppUpdatedEvent::NAME,
        AppPermissionsUpdated::NAME,
        SystemConfigChangedHook::EVENT_NAME,
    ];

    public function handles(string $eventName): bool
    {
        return true;
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return !\in_array($eventName, self::APP_EVENTS, true)
            || $subscriber->type === SubscriberType::App
            || $subscriber->type === SubscriberType::None;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        if ($webhook->appId === null) {
            return !\in_array($event->getName(), self::APP_EVENTS, true);
        }

        return $webhook->appActive || $event instanceof AppLifecycleEvent;
    }
}
