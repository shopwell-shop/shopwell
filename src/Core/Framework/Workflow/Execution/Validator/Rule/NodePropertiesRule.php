<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 *
 * 按节点类型校验必填属性。
 *
 * **这里只查"结构上必然错"的东西**（类型不认识、该选人没选）；
 * 用户 id 是否存在、角色是否还有效这类**需要查库**的判断不放这里——
 * 校验器是纯函数，查库会让单测必须起数据库，也会让保存接口变慢。
 * 那些问题在发起时由 {@see \Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry} 报。
 *
 * 错误码 `WORKFLOW_EXECUTION_006`。
 */
#[Package('framework')]
final class NodePropertiesRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($graph->nodeList() as $node) {
            $violations = [...$violations, ...match ($node->type) {
                WorkflowNodeTypes::TASK => $this->validateTask($node),
                WorkflowNodeTypes::COPY => $this->validateCopy($node),
                // 开始/结束节点没有必填属性；属性面板只让改名字
                default => [],
            }];
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function validateTask(GraphNode $node): array
    {
        $violations = [];

        if (trim((string) $node->property('name', '')) === '') {
            $violations[] = Violation::error('WORKFLOW_EXECUTION_006', '审批节点需要填写节点名称', $node->id);
        }

        $countersignType = $node->property('countersignType');

        if (!\is_string($countersignType) || !\in_array($countersignType, WorkflowCountersignTypes::ALL, true)) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                \sprintf('审批节点的多人审批方式不合法（当前：%s）', $this->describe($countersignType)),
                $node->id
            );
        }

        $assigneeType = $node->property('assigneeType');

        if (!\is_string($assigneeType) || !\in_array($assigneeType, WorkflowAssigneeTypes::ALL, true)) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                \sprintf('审批节点的审批人类型不合法（当前：%s）', $this->describe($assigneeType)),
                $node->id
            );

            return $violations;
        }

        if (!\in_array($assigneeType, WorkflowAssigneeTypes::NEEDS_VALUE, true)) {
            return $violations;
        }

        $value = $node->property('assigneeValue');

        // 只校验"非空"和"元素是字符串"：具体 id 存不存在由发起时解析器判断。
        // 空数组必须拦住——它会让 TaskExecutor 解析不出审批人，
        // 而那一步是 throw，用户要等到发起时才看到"解析不到审批人"，太晚。
        $isEmpty = !\is_array($value) || $value === [] || array_filter(
            $value,
            static fn (mixed $item): bool => \is_string($item) && $item !== ''
        ) === [];

        if ($isEmpty) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                $assigneeType === WorkflowAssigneeTypes::ROLE ? '请选择审批角色' : '请选择审批人',
                $node->id
            );
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function validateCopy(GraphNode $node): array
    {
        $violations = [];

        if (trim((string) $node->property('name', '')) === '') {
            $violations[] = Violation::error('WORKFLOW_EXECUTION_006', '抄送节点需要填写节点名称', $node->id);
        }

        $userIds = $node->property('userIdList');

        if (!\is_array($userIds) || $userIds === []) {
            // 抄送没人 = 这一步没有意义，但也不阻塞流转。
            // 判 error 而不是 warning：多半是用户忘了选，不是有意为之。
            $violations[] = Violation::error('WORKFLOW_EXECUTION_006', '抄送节点至少需要选择一个抄送人', $node->id);
        }

        return $violations;
    }

    private function describe(mixed $value): string
    {
        return \is_string($value) ? ($value === '' ? '空' : $value) : get_debug_type($value);
    }
}
