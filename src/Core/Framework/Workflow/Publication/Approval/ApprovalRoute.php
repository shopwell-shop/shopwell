<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionEvaluatorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphEdge;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowRoute;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalRoute
{
    public function __construct(private ConditionEvaluatorInterface $conditions)
    {
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{nodeIds: list<string>, edgeIds: list<string>}
     */
    public function path(GraphData $graph, array $form, ?string $stopAt = null): array
    {
        $node = $graph->singleStartNode();
        $nodes = [];
        $edges = [];
        while (true) {
            if (\in_array($node->id, $nodes, true)) {
                throw WorkflowExecutionException::invalidGraph('审批路径包含循环');
            }
            $nodes[] = $node->id;
            if ($node->id === $stopAt || $node->type === WorkflowNodeTypes::END) {
                return ['nodeIds' => $nodes, 'edgeIds' => $edges];
            }
            $next = $this->next($graph, $node->id, $form);
            if ($next === null) {
                throw WorkflowExecutionException::deadEnd($node->id);
            }
            $edges[] = $next->id;
            $node = $graph->node($next->targetNodeId);
        }
    }

    /**
     * First matching condition wins; an unconditional edge is only the fallback.
     *
     * @param array<string, mixed> $form
     */
    public function next(GraphData $graph, string $nodeId, array $form): ?GraphEdge
    {
        return (new WorkflowRoute($this->conditions))->next($graph, $nodeId, $form);
    }
}
