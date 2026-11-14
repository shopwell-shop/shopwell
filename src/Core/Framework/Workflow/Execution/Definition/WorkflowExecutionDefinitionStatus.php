<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Definition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;

/**
 * @internal
 *
 * 模板状态（`workflow_execution_definition.status`）。
 *
 * 刻意**不注册平台状态机**：设计器保存草稿极频繁，每次改动都可能写一次库，
 * 用状态机会让 `state_machine_history` 变成草稿日志，把真正要审计的审批动作淹掉。
 *
 * 代价是"谁在什么时候发布了模板"没有历史表可查。需要审计时给模板单独加
 * 状态机（只注册 draft/published/disabled 三条迁移），不影响审批引擎。
 *
 * 写保护由实体的 `Protection(write: [SYSTEM_SCOPE])` 负责，只有
 * {@see WorkflowService::publishDefinition()} 等门面方法能在 system scope 下改它。
 */
#[Package('framework')]
final class WorkflowExecutionDefinitionStatus
{
    /**
     * 草稿：可编辑，不能发起
     */
    public const DRAFT = 'draft';

    /**
     * 已发布：可发起，不可直接改图（改了要先转回草稿并升版本）
     */
    public const PUBLISHED = 'published';

    /**
     * 已停用：不能再发起，历史实例照常跑完
     */
    public const DISABLED = 'disabled';

    public const ALL = [self::DRAFT, self::PUBLISHED, self::DISABLED];
}
