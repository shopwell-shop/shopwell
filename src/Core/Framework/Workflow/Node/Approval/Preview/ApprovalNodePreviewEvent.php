<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Preview;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Contribute or replace a read-only node preview after built-in preview calculation.
 * The input graph is the published snapshot; subscribers must not write runtime state.
 * An unhandled node makes the entire path unavailable instead of omitting a step.
 */
#[Package('framework')]
final class ApprovalNodePreviewEvent extends Event
{
    /**
     * @var array{type: string, assigneeIds: list<string>, rule: string|null, branch: string|null}|null
     */
    private ?array $preview = null;

    /**
     * @internal
     */
    public function __construct(public readonly GraphNode $node, public readonly WorkflowExecutionContext $execution)
    {
    }

    /**
     * @param list<string> $assigneeIds
     */
    public function setPreview(string $type, array $assigneeIds = [], ?string $rule = null, ?string $branch = null): void
    {
        $this->preview = ['type' => $type, 'assigneeIds' => $assigneeIds, 'rule' => $rule, 'branch' => $branch];
    }

    /**
     * @return array{type: string, assigneeIds: list<string>, rule: string|null, branch: string|null}|null
     */
    public function getPreview(): ?array
    {
        return $this->preview;
    }
}
