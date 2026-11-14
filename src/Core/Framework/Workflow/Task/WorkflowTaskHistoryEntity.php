<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;

/**
 * @internal
 *
 * 历史任务：任务完成（或取消）时的归档快照，**只增不改**。
 *
 * 为什么不直接查 `workflow_task`：
 * 1. 任务可能因为退回被重开、因为加签被取消，`workflow_task` 的状态会变；
 *    而"张经理在 10:32 同意了"这件事不能变。
 * 2. 详情页的时间线要按完成时间排序，归档表上才有稳定的 `completed_at` 索引。
 * 3. 将来做审批效率统计时，归档表是唯一的干净数据源。
 *
 * 写入口只有 {@see WorkflowEngine::completeTask()}，任何接口都不暴露写。
 */
#[Package('framework')]
#[Entity('workflow_task_history', since: '6.7.15.0', collectionClass: WorkflowTaskHistoryCollection::class)]
class WorkflowTaskHistoryEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_task_history';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'workflow_task', api: true)]
    public string $taskId;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance', api: true)]
    public string $instanceId;

    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $nodeId;

    #[Field(type: FieldType::STRING, api: true, maxLength: 255)]
    public string $nodeName;

    #[Required]
    #[ForeignKey(entity: 'user', api: true)]
    public string $assigneeId;

    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $countersignType;

    /**
     * 表决结果：`agree` / `reject` / `cancel`。
     * 刻意用动作名而不是任务状态名（`agreed`/`rejected`）：归档记的是"做了什么"，
     * 任务状态记的是"现在是什么"，混用会在加签、转办加进来之后对不上。
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 16)]
    public string $decision;

    /**
     * 审批意见，可为空
     */
    #[Field(type: FieldType::TEXT, api: true)]
    public ?string $comment = null;

    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $completedAt = null;

    /**
     * 预留：退回/加签/转办产生的附加信息（原审批人、加签发起人、退回目标节点）。
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;
}
