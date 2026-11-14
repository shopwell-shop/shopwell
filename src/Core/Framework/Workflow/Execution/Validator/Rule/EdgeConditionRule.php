<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;

/**
 * @internal
 *
 * 连线条件：语法合法、变量都在表单里。
 *
 * 表达式是唯一一处"用户能写进流程引擎的代码"，所以保存时就要把它钉死：
 * 语法不对、引用了不存在的字段，一律拦住。运行期再发现，
 * 用户看到的是一句"流程无法继续"，无从下手。
 *
 * 变量白名单来自模板的 `formSchema`；没有表单字段的模板，
 * 任何条件表达式都会报错——这是对的，没有字段就没有可判断的东西。
 *
 * 错误码 `WORKFLOW_EXECUTION_007`。
 */
#[Package('framework')]
final class EdgeConditionRule implements GraphRuleInterface
{
    public function __construct(private readonly ExprNormalizer $normalizer, private readonly ApprovalCondition $conditions = new ApprovalCondition())
    {
    }

    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($graph->edges() as $edge) {
            $expr = $edge->expr();

            if ($expr === null) {
                continue;   // 无条件连线 = 默认分支，合法
            }

            try {
                if (str_starts_with($expr, ApprovalCondition::PREFIX)) {
                    $condition = $this->conditions->decode($expr);
                    $field = $condition['field'];
                    if (!$context->hasFormKey($field['key']) || ($context->formTypes[$field['key']] ?? null) !== $field['type']) {
                        throw WorkflowExecutionException::invalidGraph('条件字段与表单不一致');
                    }
                } else {
                    $this->normalizer->assertValid($expr, $context->formKeys);
                }
            } catch (WorkflowExecutionException $exception) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_007',
                    \sprintf('连线条件不合法：%s', $exception->getMessage()),
                    $edge->sourceNodeId,
                    $edge->id
                );
            }
        }

        return $violations;
    }
}
