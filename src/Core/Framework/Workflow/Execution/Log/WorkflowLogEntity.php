<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Log;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 操作日志：谁、在什么时候、对哪张单、做了什么。
 *
 * 与 `workflow_task_history` 的分工：归档表只记"任务的表决结果"，日志表记**全部**动作，
 * 包括没有任务的（发起、抄送、撤销）和将来新增的（退回、加签、转办、超时）。
 * 两者的交集是有意的——时间线读日志，统计读归档，各自只读一张表。
 *
 * `action` 与 `log_type` 分开，理由见 {@see WorkflowLogTypes}。
 */
#[Package('framework')]
#[Entity('workflow_log', since: '6.7.15.0', collectionClass: WorkflowLogCollection::class)]
class WorkflowLogEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_log';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance', api: true)]
    public string $instanceId;

    /**
     * 无任务的动作（发起、撤销）为空
     */
    #[ForeignKey(entity: 'workflow_task', api: true)]
    public ?string $taskId = null;

    /**
     * 操作人；系统动作（超时自动处理）为空
     */
    #[ForeignKey(entity: 'user', api: true)]
    public ?string $operatorId = null;

    /**
     * 动作：start / agree / reject / cancel / copy（将来：return / addSign / transfer）
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $action;

    /**
     * 动作分类，见 WorkflowLogTypes；当前只有 normal
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $logType = WorkflowLogTypes::NORMAL;

    #[Field(type: FieldType::TEXT, api: true)]
    public ?string $comment = null;

    /**
     * 动作附加上下文：抄送节点 id、退回目标节点、请求 id（幂等去重）等。
     * 同样**不要**把要过滤的字段放这里。
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: true)]
    public ?array $payload = null;
}
