<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\GraphRuleInterface;

/**
 * @internal
 *
 * 图校验：跑规则链，收集全部违规。
 *
 * **一条规则抛异常不拖垮整条链**：`try/catch` 把异常变成一条 `WORKFLOW_EXECUTION_999`。
 * 否则用户改一个错、保存一次、再看到下一个错，设计器没法用——
 * 校验器的价值就是"一次把问题给全"。
 *
 * 规则**顺序固定**，前端 `graph-validator.ts` 必须保持同样的顺序与错误码，
 * 否则同一个图两端会报不同的问题，用户不知道该信哪个。
 */
#[Package('framework')]
final class WorkflowGraphValidator
{
    /**
     * @param iterable<GraphRuleInterface> $rules
     */
    public function __construct(private readonly iterable $rules)
    {
    }

    /**
     * @return list<Violation>
     */
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($this->rules as $rule) {
            try {
                foreach ($rule->validate($graph, $context) as $violation) {
                    $violations[] = $violation;
                }
            } catch (\Throwable $exception) {
                // 保留类名便于定位是哪条规则炸了；不暴露堆栈给前端
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_999',
                    \sprintf('校验规则 %s 执行失败：%s', $exception::class, $exception->getMessage())
                );
            }
        }

        return $violations;
    }

    /**
     * 只关心"能不能保存/发布"时用这个：有 error 就不过，warning 放行。
     *
     * @param list<Violation> $violations
     */
    public function hasErrors(array $violations): bool
    {
        foreach ($violations as $violation) {
            if ($violation->isError()) {
                return true;
            }
        }

        return false;
    }
}
