<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @final
 */
#[Package('framework')]
class ApprovalAssigneeRegistry
{
    /**
     * @var array<string, ApprovalAssigneeSource>
     */
    private array $sources = [];

    /**
     * @internal
     *
     * @param iterable<ApprovalAssigneeSource> $sources
     */
    public function __construct(iterable $sources)
    {
        foreach ($sources as $source) {
            $name = $source->getName();

            if (!preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $name)) {
                throw WorkflowException::invalidDocument('/assignee/source', 'An assignee source requires a namespaced identifier.');
            }

            if (isset($this->sources[$name])) {
                throw WorkflowExecutionException::duplicateRegistration('approval assignee source', $name);
            }

            $this->sources[$name] = $source;
        }
    }

    public function get(string $name): ApprovalAssigneeSource
    {
        return $this->sources[$name] ?? throw WorkflowException::invalidDocument('/assignee/source', 'The assignee source is unavailable: ' . $name);
    }
}
