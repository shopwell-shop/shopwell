<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\Event\AdminMenuVisibilityEvent;
use Shopwell\Core\System\AdminMenu\Subscriber\WorkflowMenuVisibilitySubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(WorkflowMenuVisibilitySubscriber::class)]
class WorkflowMenuVisibilitySubscriberTest extends TestCase
{
    public function testHidesLegacyWorkflowEntriesWithoutHidingApprovalOrFlowBuilder(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new WorkflowMenuVisibilitySubscriber());
        $event = new AdminMenuVisibilityEvent(Context::createDefaultContext(), 'zh-CN');

        $dispatcher->dispatch($event);

        foreach (['sw-workflow', 'sw-workflow-design', 'sw-workflow-tasks'] as $key) {
            static::assertTrue($event->isHidden($key));
        }
        static::assertFalse($event->isHidden('sw-approval-flow'));
        static::assertFalse($event->isHidden('sw-flow'));
        static::assertFalse($event->isHidden('sw-finance-transfer'));
    }
}
