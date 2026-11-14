<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 *
 * 有且仅有一个结束节点，且结束节点没有出边。
 *
 * 结束节点带出边是最容易被忽略的一种错：图上看着"走到结束了"，
 * 实际引擎进到 end 就收口，后面的节点永远执行不到，用户会以为"漏审了一步"。
 *
 * 错误码 `WORKFLOW_EXECUTION_003`。
 */
#[Package('framework')]
final class SingleEndRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];
        $ends = $graph->nodesOfType(WorkflowNodeTypes::END);

        if (\count($ends) === 0) {
            $violations[] = Violation::error('WORKFLOW_EXECUTION_003', '缺少结束节点');
        }

        if (\count($ends) > 1) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_003',
                \sprintf('结束节点只能有一个，当前有 %d 个（分支请汇聚到同一个结束节点）', \count($ends)),
                $ends[1]->id
            );
        }

        foreach ($ends as $end) {
            if ($graph->outgoing($end->id) !== []) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_003',
                    '结束节点不能再有出边，它后面的节点永远不会执行',
                    $end->id
                );
            }
        }

        return $violations;
    }
}
