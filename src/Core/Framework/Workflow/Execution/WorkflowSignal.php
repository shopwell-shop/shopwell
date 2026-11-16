<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * A signal resumes one waiting node execution; it never selects a node or changes the graph.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WorkflowSignal
{
    public const TASK_COMPLETED = 'core:task-completed';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public string $name, public array $payload = [])
    {
    }
}
