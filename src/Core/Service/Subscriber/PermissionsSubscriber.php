<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Event\PermissionsGrantedEvent;
use Shopwell\Core\Service\Event\PermissionsRevokedEvent;
use Shopwell\Core\Service\ServiceLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsSubscriber implements EventSubscriberInterface
{
    public function __construct(private ServiceLifecycle $serviceLifecycle)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsGrantedEvent::class => 'reevaluateServices',
            PermissionsRevokedEvent::class => 'reevaluateServices',
        ];
    }

    public function reevaluateServices(PermissionsGrantedEvent|PermissionsRevokedEvent $event): void
    {
        $this->serviceLifecycle->reevaluateInstalled($event->getContext());
    }
}
