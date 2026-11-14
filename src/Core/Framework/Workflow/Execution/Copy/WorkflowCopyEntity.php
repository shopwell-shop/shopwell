<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Copy;

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
 * 抄送记录：谁在哪一步被抄送了、读没读。
 *
 * 单独一张表而不是塞进 `workflow_log.payload`：抄送要支持"我的抄送"列表、
 * 未读数、已读回执，这些都要能按 `user_id` + `read_at` 过滤，JSON 列做不到。
 *
 * 抄送**不生成任务**、不阻塞流转（见 {@see Executor\CopyExecutor}），
 * 所以这张表里没有状态机，`read_at` 为空就是未读。
 */
#[Package('framework')]
#[Entity('workflow_copy', since: '6.7.15.0', collectionClass: WorkflowCopyCollection::class)]
class WorkflowCopyEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_copy';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance', api: true)]
    public string $instanceId;

    /**
     * 抄送发生在哪个节点
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $nodeId;

    #[Required]
    #[ForeignKey(entity: 'user', api: true)]
    public string $userId;

    /**
     * 为空 = 未读
     */
    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $readAt = null;

    /**
     * 预留：抄送渠道（站内/短信/邮件）、是否强制阅读。
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;
}
