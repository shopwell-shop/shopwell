<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRoleInstaller;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowDefaultRolesSubscriber::class)]
class WorkflowDefaultRolesSubscriberTest extends TestCase
{
    public function testSystemInstallUsesInstallationContext(): void
    {
        $context = Context::createDefaultContext();
        $installer = $this->createMock(WorkflowDefaultRoleInstaller::class);
        $installer->expects($this->once())->method('install')->with($context)->willReturn(2);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new WorkflowDefaultRolesSubscriber($installer));

        $dispatcher->dispatch(new SystemInstallCompletedEvent($context));
    }
}
