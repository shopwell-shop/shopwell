<?php declare(strict_types=1);

namespace Shopwell\Core\Service;

use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\Policy;
use Shopwell\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopwell\Core\Framework\Webhook\Hookable;
use Shopwell\Core\Framework\Webhook\Webhook;
use Shopwell\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * Restricts the events that carry service data to self-managed apps.
 *
 * @internal
 */
#[Package('framework')]
final class ServiceWebhookPolicy implements Policy
{
    public function __construct(private readonly ActiveAppsLoader $activeAppsLoader)
    {
    }

    public function handles(string $eventName): bool
    {
        return $eventName === CommercialLicenseProvidedEvent::NAME;
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return $subscriber->manifest?->getMetadata()->isSelfManaged() ?? false;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        if ($webhook->appName === null) {
            return false;
        }

        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            if ($app['name'] === $webhook->appName) {
                return $app['selfManaged'];
            }
        }

        return false;
    }
}
