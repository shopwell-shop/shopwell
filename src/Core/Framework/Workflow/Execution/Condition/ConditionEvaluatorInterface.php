<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal Experimental execution extension point; not covered by the backward compatibility promise.
 *
 * 条件求值器。
 *
 * 上层（引擎、校验器）只依赖这个接口，不认具体实现。
 * 支持的表达式语法由注册的实现定义。
 */
#[Package('framework')]
interface ConditionEvaluatorInterface
{
    /**
     * 求一条**契约表达式**（`${form.amount} > 5000`）在给定表单数据下是否为真。
     *
     * @param array<string, mixed> $formData
     *
     * @throws WorkflowExecutionException 表达式无法解析时抛，**不要**把解析失败当成 false：
     *                                    那样分支会静默走默认路，症状是"条件明明配了却不生效"
     */
    public function evaluate(string $expr, array $formData): bool;
}
