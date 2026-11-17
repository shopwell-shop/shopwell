<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Deliver a committed reminder through additional channels.
 * Delivery is at least once; deduplicate external effects using deliveryId.
 * Throw on failure so the scheduled task retries the same delivery.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ApprovalReminderDeliveryEvent extends Event
{
    /**
     * @internal
     */
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $taskId,
        public readonly string $instanceId,
        public readonly string $assigneeId,
        public readonly string $source,
        public readonly \DateTimeInterface $requestedAt,
        public readonly ?\DateTimeInterface $dueAt,
    ) {
    }
}
