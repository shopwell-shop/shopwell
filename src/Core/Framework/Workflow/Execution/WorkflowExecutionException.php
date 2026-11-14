<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * 审批域异常。
 *
 * 全部用静态构造器：错误码在代码里只出现一次，前端和日志按码匹配，
 * 不按异常文案匹配（文案随时会改）。
 *
 * 用 {@see WorkflowException} 而不是裸 `\RuntimeException`：控制器里不写 try/catch，
 * 异常直接变成带 HTTP 状态的标准错误响应，与主系统其它域一致。
 */
#[Package('framework')]
class WorkflowExecutionException extends WorkflowException
{
    public const INVALID_GRAPH = 'FRAMEWORK__WORKFLOW_EXECUTION_INVALID_GRAPH';
    public const UNKNOWN_NODE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNKNOWN_NODE';
    public const DEAD_END = 'FRAMEWORK__WORKFLOW_EXECUTION_DEAD_END';
    public const UNSUPPORTED_NODE_TYPE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNSUPPORTED_NODE_TYPE';
    public const UNSUPPORTED_COUNTERSIGN_TYPE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNSUPPORTED_COUNTERSIGN_TYPE';
    public const UNSUPPORTED_ASSIGNEE_TYPE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNSUPPORTED_ASSIGNEE_TYPE';
    public const NO_ASSIGNEE = 'FRAMEWORK__WORKFLOW_EXECUTION_NO_ASSIGNEE';
    public const GRAPH_INVALID = 'FRAMEWORK__WORKFLOW_EXECUTION_GRAPH_INVALID';
    public const DEFINITION_NOT_FOUND = 'FRAMEWORK__WORKFLOW_EXECUTION_DEFINITION_NOT_FOUND';
    public const DEFINITION_NOT_PUBLISHED = 'FRAMEWORK__WORKFLOW_EXECUTION_DEFINITION_NOT_PUBLISHED';
    public const INSTANCE_NOT_FOUND = 'FRAMEWORK__WORKFLOW_EXECUTION_INSTANCE_NOT_FOUND';
    public const TASK_NOT_FOUND = 'FRAMEWORK__WORKFLOW_EXECUTION_TASK_NOT_FOUND';
    public const TASK_NOT_YOURS = 'FRAMEWORK__WORKFLOW_EXECUTION_TASK_NOT_YOURS';
    public const INSTANCE_NOT_RUNNING = 'FRAMEWORK__WORKFLOW_EXECUTION_INSTANCE_NOT_RUNNING';
    public const CONDITION_INVALID = 'FRAMEWORK__WORKFLOW_EXECUTION_CONDITION_INVALID';
    public const ACTOR_REQUIRED = 'FRAMEWORK__WORKFLOW_EXECUTION_ACTOR_REQUIRED';
    public const TASK_ALREADY_HANDLED = 'FRAMEWORK__WORKFLOW_EXECUTION_TASK_ALREADY_HANDLED';
    public const COMMENT_REQUIRED = 'FRAMEWORK__WORKFLOW_EXECUTION_COMMENT_REQUIRED';
    public const FORBIDDEN = 'FRAMEWORK__WORKFLOW_EXECUTION_FORBIDDEN';

    public static function invalidGraph(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_GRAPH,
            '流程图数据不合法：{{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function unknownNode(string $nodeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_NODE,
            '流程图里不存在节点 "{{ nodeId }}"',
            ['nodeId' => $nodeId]
        );
    }

    public static function deadEnd(string $nodeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DEAD_END,
            '节点 "{{ nodeId }}" 没有可走的出边，流程无法继续',
            ['nodeId' => $nodeId]
        );
    }

    public static function unsupportedNodeType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_NODE_TYPE,
            '不支持的节点类型 "{{ type }}"',
            ['type' => $type]
        );
    }

    public static function unsupportedCountersignType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_COUNTERSIGN_TYPE,
            '不支持的多人审批方式 "{{ type }}"',
            ['type' => $type]
        );
    }

    public static function unsupportedAssigneeType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_ASSIGNEE_TYPE,
            '不支持的审批人类型 "{{ type }}"',
            ['type' => $type]
        );
    }

    /**
     * 审批人解析为空。
     *
     * 必须立刻失败：默默跳过这个节点等于审批被绕过，而绕过在界面上看不出来
     * （流程照常往下走，只是没有人审）。
     */
    public static function noAssignee(string $nodeId, string $assigneeType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_ASSIGNEE,
            '节点 "{{ nodeId }}" 解析不到审批人（类型 {{ assigneeType }}）',
            ['nodeId' => $nodeId, 'assigneeType' => $assigneeType]
        );
    }

    /**
     * @param list<array{code: string, message: string, nodeId?: string|null, edgeId?: string|null}> $violations
     */
    public static function graphInvalid(array $violations): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::GRAPH_INVALID,
            '流程图校验未通过：{{ message }}',
            [
                'message' => implode('；', array_map(
                    static fn (array $v): string => $v['message'],
                    $violations
                )),
                'violations' => $violations,
            ]
        );
    }

    public static function definitionNotFound(string $definitionId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DEFINITION_NOT_FOUND,
            '流程模板 "{{ definitionId }}" 不存在',
            ['definitionId' => $definitionId]
        );
    }

    public static function definitionNotPublished(string $definitionId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::DEFINITION_NOT_PUBLISHED,
            '流程模板 "{{ definitionId }}" 尚未发布，不能发起',
            ['definitionId' => $definitionId]
        );
    }

    public static function instanceNotFound(string $instanceId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::INSTANCE_NOT_FOUND,
            '审批实例 "{{ instanceId }}" 不存在',
            ['instanceId' => $instanceId]
        );
    }

    public static function taskNotFound(string $taskId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TASK_NOT_FOUND,
            '审批任务 "{{ taskId }}" 不存在',
            ['taskId' => $taskId]
        );
    }

    /**
     * 别人的任务一律回 404 而不是 403：403 会告诉调用方"这条任务存在"，
     * 配合 id 枚举就能数出别的部门有多少单。
     */
    public static function taskNotYours(string $taskId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TASK_NOT_YOURS,
            '审批任务 "{{ taskId }}" 不存在或不属于当前用户',
            ['taskId' => $taskId]
        );
    }

    public static function instanceNotRunning(string $instanceId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INSTANCE_NOT_RUNNING,
            '审批实例 "{{ instanceId }}" 已结束，不能再操作',
            ['instanceId' => $instanceId]
        );
    }

    public static function conditionInvalid(string $expr, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONDITION_INVALID,
            '条件表达式 "{{ expr }}" 不合法：{{ reason }}',
            ['expr' => $expr, 'reason' => $reason]
        );
    }

    public static function actorRequired(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::ACTOR_REQUIRED,
            '当前请求没有操作人，无法执行审批动作',
            []
        );
    }

    /**
     * 任务已经被处理过（重复点击、或两个人同时抢同一条待办）。
     *
     * 比让状态机抛裸异常好在：前端能拿到稳定的错误码，据此提示"这条已处理"，
     * 而不是显示一句数据库层面的迁移失败。
     */
    public static function taskAlreadyHandled(string $taskId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TASK_ALREADY_HANDLED,
            '审批任务 "{{ taskId }}" 已被处理',
            ['taskId' => $taskId]
        );
    }

    /**
     * 拒绝必须写意见：不写理由的拒绝，发起人无法知道要改什么
     */
    public static function commentRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_REQUIRED,
            '请填写审批意见',
            []
        );
    }

    /**
     * 无权查看/操作这条单据（不是"不存在"，但对外也不该暴露存在性时用它）
     */
    public static function forbidden(string $reason): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::FORBIDDEN,
            '无权操作：{{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function duplicateRegistration(string $kind, string $name): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_EXECUTION_DUPLICATE_REGISTRATION', 'Workflow {{ kind }} "{{ name }}" is registered more than once.', ['kind' => $kind, 'name' => $name]);
    }

    public static function stateNotLoaded(): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_EXECUTION_STATE_NOT_LOADED', 'Load the stateMachineState association before reading the workflow state.');
    }

    public static function invalidSignal(string $name): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_EXECUTION_INVALID_SIGNAL', 'The node is not waiting for signal "{{ name }}".', ['name' => $name]);
    }

    public static function executionNotWaiting(string $id): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_EXECUTION_NOT_WAITING', 'Node execution "{{ id }}" is not waiting.', ['id' => $id]);
    }
}
