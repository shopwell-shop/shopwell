<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Context as PlatformContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 *
 * Immutable execution inputs shared by the scheduler and node executors.
 * Actor identity comes from the authenticated Context; executors do not read HTTP requests.
 */
#[Package('framework')]
final class WorkflowExecutionContext
{
    public function __construct(
        private readonly GraphData $graph,
        private readonly WorkflowInstanceEntity $instance,
        private readonly PlatformContext $context,
        private readonly ?string $actorId,
        private readonly int $round,
        private readonly ?string $executionId = null,
    ) {
    }

    public function graph(): GraphData
    {
        return $this->graph;
    }

    public function instance(): WorkflowInstanceEntity
    {
        return $this->instance;
    }

    public function context(): PlatformContext
    {
        return $this->context;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    /**
     * Require an authenticated actor for operations that cannot run as a system task.
     */
    public function requireActor(): string
    {
        return $this->actorId ?? throw WorkflowExecutionException::actorRequired();
    }

    public function round(): int
    {
        return $this->round;
    }

    /**
     * Return a new context with the refreshed instance and unchanged execution data.
     */
    public function withInstance(WorkflowInstanceEntity $instance): self
    {
        return new self($this->graph, $instance, $this->context, $this->actorId, $this->round, $this->executionId);
    }

    public function executionId(): string
    {
        return $this->executionId ?? throw WorkflowExecutionException::invalidGraph('The node has no execution identity.');
    }

    public function withNodeExecution(string $executionId): self
    {
        return new self($this->graph, $this->instance, $this->context, $this->actorId, $this->round, $executionId);
    }
}
