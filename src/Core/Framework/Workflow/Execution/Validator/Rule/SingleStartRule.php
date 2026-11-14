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
 * 有且仅有一个开始节点。
 *
 * 0 个 = 流程不知道从哪开始；2 个以上 = 引擎的 `singleStartNode()` 会抛异常，
 * 但那是运行期报错，用户在设计器里看不到原因，所以保存时就要拦住。
 *
 * 错误码 `WORKFLOW_EXECUTION_002`。
 */
#[Package('framework')]
final class SingleStartRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $starts = $graph->nodesOfType(WorkflowNodeTypes::START);

        return match (\count($starts)) {
            1 => [],
            0 => [Violation::error('WORKFLOW_EXECUTION_002', '缺少开始节点')],
            default => [Violation::error(
                'WORKFLOW_EXECUTION_002',
                \sprintf('开始节点只能有一个，当前有 %d 个', \count($starts)),
                $starts[1]->id
            )],
        };
    }
}
