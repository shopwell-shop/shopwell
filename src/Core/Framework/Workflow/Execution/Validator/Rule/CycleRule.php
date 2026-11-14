<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;

/**
 * @internal
 *
 * 不能有环（三色标记 DFS）。
 *
 * 环在审批流里不是"性能问题"，而是**永远走不完**：A→B→A，
 * 引擎的 `advanceWhile` 会一直往下走，直到请求超时或者栈被撑爆。
 * 前端设计器已经用插入式交互让它画不出环，但后端不能依赖前端——
 * 接口可以被直接调用，历史数据也可能来自旧版本。
 *
 * 为什么用三色（白/灰/黑）而不是"记录访问过的节点"：
 * 后者会把"两条路都到同一个节点"（菱形，合法的）误判成环。
 * 只有走到**当前递归栈上**的节点才是环。
 *
 * 错误码 `WORKFLOW_EXECUTION_004`。
 */
#[Package('framework')]
final class CycleRule implements GraphRuleInterface
{
    private const WHITE = 0;
    private const GRAY = 1;
    private const BLACK = 2;

    public function validate(GraphData $graph, ValidationContext $context): array
    {
        /** @var array<string, int> $colors */
        $colors = [];
        /** @var list<string> $path */
        $path = [];
        $violations = [];
        $reported = [];

        $visit = function (string $nodeId) use (&$visit, &$colors, &$path, &$violations, &$reported, $graph): void {
            $colors[$nodeId] = self::GRAY;
            $path[] = $nodeId;

            foreach ($graph->outgoing($nodeId) as $edge) {
                $target = $edge->targetNodeId;

                if (!$graph->hasNode($target)) {
                    continue;   // 悬空边由 ShapeRule 报，不在这里重复
                }

                $color = $colors[$target] ?? self::WHITE;

                if ($color === self::GRAY) {
                    // 从路径里截出环：从 target 第一次出现的位置到当前节点
                    $start = array_search($target, $path, true);
                    $cycle = $start === false ? [$target] : \array_slice($path, (int) $start);
                    $cycle[] = $target;

                    // 同一个环可能被多条入边各发现一次，按节点集合去重
                    $key = implode('>', array_unique($cycle));

                    if (!isset($reported[$key])) {
                        $reported[$key] = true;
                        $violations[] = Violation::error(
                            'WORKFLOW_EXECUTION_004',
                            \sprintf('流程存在环：%s', implode(' → ', $cycle)),
                            $target
                        );
                    }

                    continue;
                }

                if ($color === self::WHITE) {
                    $visit($target);
                }
            }

            array_pop($path);
            $colors[$nodeId] = self::BLACK;
        };

        foreach ($graph->nodeList() as $node) {
            if (($colors[$node->id] ?? self::WHITE) === self::WHITE) {
                $visit($node->id);
            }
        }

        return $violations;
    }
}
