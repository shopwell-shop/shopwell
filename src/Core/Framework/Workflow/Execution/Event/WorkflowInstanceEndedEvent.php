<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * An instance finished; use this event for final business updates, notifications and cleanup.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowInstanceEndedEvent extends Event
{
    final public const EVENT_NAME = 'shopwell.workflow.execution.instance.ended';

    public function __construct(
        private readonly string $instanceId,
        private readonly string $result,
    ) {
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getResult(): string
    {
        return $this->result;
    }
}
