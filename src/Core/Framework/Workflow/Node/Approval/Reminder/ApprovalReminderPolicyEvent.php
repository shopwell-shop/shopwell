<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Narrow reminder eligibility after ownership, lifecycle and cooldown checks.
 * Subscribers must be read-only: presentation and locked execution both use this event.
 */
#[Package('framework')]
final class ApprovalReminderPolicyEvent extends Event
{
    private bool $allowed = true;

    /**
     * @internal
     */
    public function __construct(
        public readonly WorkflowTaskEntity $task,
        public readonly WorkflowInstanceEntity $instance,
        public readonly string $source,
        public readonly Context $context,
    ) {
    }

    public function deny(): void
    {
        $this->allowed = false;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
