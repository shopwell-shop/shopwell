<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * One task represents one assignee's decision in a node and round; sequential approval uses the immutable ext.assignees snapshot.
 *
 * @internal
 */
#[Package('framework')]
#[Entity('workflow_task', since: '6.7.15.0', collectionClass: WorkflowTaskCollection::class)]
class WorkflowTaskEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_task';

    /**
     * The ext key containing the assignee snapshot.
     */
    public const EXT_ASSIGNEES = 'assignees';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[State(machine: WorkflowTaskStates::STATE_MACHINE, api: true)]
    public string $stateId;

    #[ManyToOne(entity: 'state_machine_state', api: true, column: 'state_id')]
    public ?StateMachineStateEntity $stateMachineState = null;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance', api: true)]
    public string $instanceId;

    /**
     * The definition node ID, matching node.id in the graph.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $nodeId;

    /**
     * Node name at creation, preserved when the definition is renamed.
     */
    #[Field(type: FieldType::STRING, api: true, maxLength: 255)]
    public string $nodeName;

    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $countersignType;

    #[Required]
    #[Field(type: FieldType::INT, api: true)]
    public int $round = 1;

    /**
     * Sequential approval position starting at one; other modes use one.
     */
    #[Field(type: FieldType::INT, api: true)]
    public int $sequence = 1;

    #[Required]
    #[ForeignKey(entity: 'user', api: true)]
    public string $assigneeId;

    /**
     * The assignee source type for presentation and diagnostics.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $assigneeType;

    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $completedAt = null;

    /**
     * Reserved transfer and delegation metadata; assignees is the ordered snapshot key.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;

    public function state(): string
    {
        return $this->stateMachineState?->getTechnicalName()
            ?? throw WorkflowExecutionException::stateNotLoaded();
    }

    public function isTodo(): bool
    {
        return $this->state() === WorkflowTaskStates::TODO;
    }

    /**
     * Return the sequential approval assignee snapshot.
     *
     * @return list<string>
     */
    public function assigneeSnapshot(): array
    {
        $snapshot = $this->ext[self::EXT_ASSIGNEES] ?? [];

        if (!\is_array($snapshot)) {
            return [];
        }

        return array_values(array_filter($snapshot, static fn (mixed $id): bool => \is_string($id) && $id !== ''));
    }
}
