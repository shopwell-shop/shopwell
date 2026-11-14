<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 审批人类型（`node.properties.assigneeType`）。
 *
 * 每种类型对应一个 {@see Assignee\AssigneeResolverInterface} 实现，
 * 由 {@see Assignee\AssigneeResolverRegistry} 按 `getAssigneeType()` 分发。
 * Core registers user, role and initiator resolvers. Organizational sources require
 * an extension to register its resolver before those graphs can execute.
 */
#[Package('framework')]
final class WorkflowAssigneeTypes
{
    /**
     * 指定用户：`assigneeValue` 是用户 id 数组
     */
    public const USER = 'user';

    /**
     * 发起人本人：`assigneeValue` 留空
     */
    public const INITIATOR = 'initiator';

    /**
     * 发起人直属上级：需要扩展注册对应的审批人解析器
     */
    public const LEADER = 'leader';

    /**
     * 角色：`assigneeValue` 是 ACL 角色 id（复用平台 acl_role，不自建角色表）
     */
    public const ROLE = 'role';

    /**
     * 部门负责人：需要扩展注册对应的审批人解析器
     */
    public const UNIT_LEADER = 'unitLeader';

    public const ALL = [self::USER, self::INITIATOR, self::LEADER, self::ROLE, self::UNIT_LEADER];

    /**
     * 需要 `assigneeValue` 才有意义的类型；其余类型的值必须为空
     */
    public const NEEDS_VALUE = [self::USER, self::ROLE];

    /**
     * 预留（当前不实现）：FORM_FIELD（表单里的选人字段）、
     * INITIATOR_LEADER_LEVEL2（上级的上级）、DEPT_LEADER_OF_USER（某人所在部门负责人）。
     */
    public const FUTURE_FORM_FIELD = 'formField';
    public const FUTURE_LEADER_LEVEL2 = 'leaderLevel2';
}
