<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Find tagged executors by node type; keep the cache on the service instance.
 *
 * @internal
 */
#[Package('framework')]
final class NodeExecutorRegistry
{
    /**
     * @var array<string, NodeExecutorInterface>|null
     */
    private ?array $resolved = null;

    /**
     * @param iterable<NodeExecutorInterface> $executors
     */
    public function __construct(private readonly iterable $executors)
    {
    }

    public function for(string $nodeType): NodeExecutorInterface
    {
        return $this->map()[$nodeType] ?? throw WorkflowExecutionException::unsupportedNodeType($nodeType);
    }

    public function has(string $nodeType): bool
    {
        return isset($this->map()[$nodeType]);
    }

    /**
     * @return array<string, NodeExecutorInterface>
     */
    private function map(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $map = [];

        foreach ($this->executors as $executor) {
            $type = $executor->getNodeType();

            // Reject duplicate registrations before one executor can silently replace another.
            if (isset($map[$type])) {
                throw WorkflowExecutionException::duplicateRegistration('runtime provider', $type);
            }

            $map[$type] = $executor;
        }

        return $this->resolved = $map;
    }
}
