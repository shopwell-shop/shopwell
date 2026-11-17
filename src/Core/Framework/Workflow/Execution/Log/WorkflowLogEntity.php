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
 * Record all actions, including those without tasks; task history separately stores immutable decision snapshots.
 *
 * @internal
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
     * Null for actions without a task, such as start or cancel.
     */
    #[ForeignKey(entity: 'workflow_task', api: true)]
    public ?string $taskId = null;

    /**
     * The actor; null for system actions.
     */
    #[ForeignKey(entity: 'user', api: true)]
    public ?string $operatorId = null;

    /**
     * Action name: start, agree, reject, cancel or copy.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $action;

    /**
     * Action category defined by WorkflowLogTypes.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $logType = WorkflowLogTypes::NORMAL;

    #[Field(type: FieldType::TEXT, api: true)]
    public ?string $comment = null;

    /**
     * Additional context; keep fields used for filtering in dedicated columns.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $payload = null;
}
