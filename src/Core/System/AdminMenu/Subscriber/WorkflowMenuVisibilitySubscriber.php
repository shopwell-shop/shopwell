<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\Event\AdminMenuVisibilityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hides the legacy entry during the transition to Approval Flow, including moved child entries.
 * Routes and business execution remain available until the replacement is complete.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class WorkflowMenuVisibilitySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuVisibilityEvent::class => 'hideWorkflow'];
    }

    public function hideWorkflow(AdminMenuVisibilityEvent $event): void
    {
        $event->hide('sw-workflow', 'sw-workflow-design', 'sw-workflow-tasks');
    }
}
