<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Restrict configured operations for both presentation and locked execution.
 * Subscribers must be read-only: this event also runs while listing tasks.
 */
#[Package('framework')]
final class ApprovalTaskActionsEvent extends Event
{
    /**
     * @internal
     *
     * @param list<string> $actions
     */
    public function __construct(
        public readonly WorkflowTaskEntity $task,
        public readonly WorkflowInstanceEntity $instance,
        public readonly Context $context,
        private array $actions,
    ) {
    }

    public function deny(string $action): void
    {
        $this->actions = array_values(array_diff($this->actions, [$action]));
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return $this->actions;
    }
}
