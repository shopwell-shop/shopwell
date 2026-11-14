<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeException;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatus;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatusesEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowRuntimeStatusesEvent::class)]
class WorkflowRuntimeStatusesEventTest extends TestCase
{
    public function testRuntimeCanContributeAStatusForTheRequestedBinding(): void
    {
        $event = new WorkflowRuntimeStatusesEvent(['instance' => ['releaseId' => 'release', 'publisher' => 'custom:approval']], Context::createDefaultContext());
        static::assertSame([], $event->getStatuses());
        $status = new WorkflowRuntimeStatus('approved');
        $event->setStatus('instance', $status);
        static::assertSame(['instance' => ['status' => $status, 'currentAssignees' => []]], $event->getStatuses());
    }

    public function testRuntimeCannotAddAnUnrequestedInstance(): void
    {
        $event = new WorkflowRuntimeStatusesEvent([], Context::createDefaultContext());
        $this->expectExceptionObject(WorkflowRuntimeException::invalidReference());
        $event->setStatus('other', new WorkflowRuntimeStatus('approved'));
    }
}
