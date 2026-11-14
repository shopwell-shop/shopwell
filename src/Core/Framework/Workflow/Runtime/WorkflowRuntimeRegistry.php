<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Runtime;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowRuntimeRegistry
{
    /**
     * @var array<string, WorkflowRuntime>
     */
    private array $runtimes = [];

    /**
     * @param iterable<WorkflowRuntime> $runtimes
     */
    public function __construct(iterable $runtimes)
    {
        foreach ($runtimes as $runtime) {
            if (isset($this->runtimes[$runtime->name()])) {
                throw WorkflowRuntimeException::duplicate($runtime->name());
            }
            $this->runtimes[$runtime->name()] = $runtime;
        }
    }

    public function has(string $publisher): bool
    {
        return isset($this->runtimes[$publisher]);
    }

    public function get(string $publisher): WorkflowRuntime
    {
        return $this->runtimes[$publisher] ?? throw WorkflowRuntimeException::unavailable($publisher);
    }
}
