<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after commit; listeners can read the new instance and should reuse inner services rather than public transaction boundaries.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowStartedEvent extends Event
{
    final public const EVENT_NAME = 'shopwell.workflow.execution.started';

    public function __construct(
        private readonly string $instanceId,
        private readonly string $definitionId,
        private readonly string $initiatorId,
        private readonly string $title,
    ) {
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getInitiatorId(): string
    {
        return $this->initiatorId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
