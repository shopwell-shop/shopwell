<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Diagnostics\Policy;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowGraphPolicyRegistry
{
    /**
     * @var array<string, WorkflowGraphPolicy>
     */
    private array $policies = [];

    /**
     * @param iterable<WorkflowGraphPolicy> $policies
     */
    public function __construct(iterable $policies)
    {
        foreach ($policies as $policy) {
            $name = $policy->getName();
            if (isset($this->policies[$name])) {
                throw WorkflowException::duplicateGraphPolicy($name);
            }
            $this->policies[$name] = $policy;
        }
    }

    public function get(string $name): WorkflowGraphPolicy
    {
        return $this->policies[$name] ?? throw WorkflowException::invalidDocument('/policy', 'Unknown workflow graph policy: ' . $name);
    }
}
