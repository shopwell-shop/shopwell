<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Event\ShopwellAccountLoginEvent;
use Shopwell\Core\Framework\Store\Event\ShopwellAccountLogoutEvent;
use Shopwell\Core\Service\ServiceLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ShopwellAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ServiceLifecycle $serviceLifecycle,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShopwellAccountLoginEvent::class => 'reevaluateServices',
            ShopwellAccountLogoutEvent::class => 'reevaluateServices',
        ];
    }

    public function reevaluateServices(ShopwellAccountLoginEvent|ShopwellAccountLogoutEvent $event): void
    {
        $this->serviceLifecycle->reevaluateInstalled($event->getContext());
    }
}
