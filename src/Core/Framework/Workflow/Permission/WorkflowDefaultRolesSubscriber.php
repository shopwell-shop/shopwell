<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Permission;

use Shopwell\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowDefaultRolesSubscriber implements EventSubscriberInterface
{
    public function __construct(private WorkflowDefaultRoleInstaller $installer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [SystemInstallCompletedEvent::class => 'onInstallCompleted'];
    }

    public function onInstallCompleted(SystemInstallCompletedEvent $event): void
    {
        $this->installer->install($event->getContext());
    }
}
