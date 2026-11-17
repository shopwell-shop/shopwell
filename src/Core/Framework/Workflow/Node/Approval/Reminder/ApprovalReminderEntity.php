<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

/**
 * One private reminder receipt per task; its ID equals the task ID.
 * Delivery IDs survive retries so external channels can deduplicate their effects.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('workflow_approval_reminder', since: '6.7.15.0', collectionClass: ApprovalReminderCollection::class)]
class ApprovalReminderEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_approval_reminder';
    public const MANUAL = 'manual';
    public const OVERDUE = 'overdue';
    public const COOLDOWN_SECONDS = 600;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'workflow_task')]
    public string $taskId;

    #[ManyToOne(entity: 'workflow_task', column: 'task_id')]
    public ?WorkflowTaskEntity $task = null;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance')]
    public string $instanceId;

    #[ManyToOne(entity: 'workflow_instance', column: 'instance_id')]
    public ?WorkflowInstanceEntity $instance = null;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeInterface $dueAt = null;

    #[Required]
    #[Field(type: FieldType::BOOL)]
    public bool $remindOnOverdue = false;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeInterface $overdueRemindedAt = null;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeInterface $lastRemindedAt = null;

    #[Field(type: FieldType::STRING, maxLength: 16)]
    public ?string $source = null;

    #[Required]
    #[Field(type: FieldType::INT)]
    public int $reminderCount = 0;

    #[Field(type: FieldType::UUID)]
    public ?string $deliveryId = null;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeInterface $deliveredAt = null;
}
