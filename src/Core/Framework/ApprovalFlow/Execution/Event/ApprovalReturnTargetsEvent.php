<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * Allows listeners to narrow the valid historical targets for a pending personal task.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 *
 * @phpstan-type Target array{executionId: string, nodeId: string, sequence: int}
 */
#[Package('framework')]
final class ApprovalReturnTargetsEvent implements ShopwellEvent
{
    /**
     * @internal
     *
     * @param list<Target> $targets
     */
    public function __construct(
        public readonly string $instanceId,
        public readonly string $releaseId,
        public readonly string $taskId,
        public readonly string $nodeId,
        private readonly Context $context,
        private array $targets,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return list<Target>
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * @param list<string> $executionIds
     */
    public function restrictTo(array $executionIds): void
    {
        $this->targets = array_values(array_filter(
            $this->targets,
            static fn (array $target): bool => \in_array($target['executionId'], $executionIds, true),
        ));
    }
}
