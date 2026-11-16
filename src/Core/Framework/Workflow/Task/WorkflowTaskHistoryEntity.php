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

/**
 * Append-only task completion snapshots preserve decisions independently of mutable tasks.
 *
 * @internal
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
     * Decision action: agree, reject or cancel; distinct from the task's current state.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 16)]
    public string $decision;

    /**
     * Optional approval comment.
     */
    #[Field(type: FieldType::TEXT, api: true)]
    public ?string $comment = null;

    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $completedAt = null;

    /**
     * Reserved metadata for returns, extra approval steps and transfers.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;
}
