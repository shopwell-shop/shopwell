<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Instance;

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
 * @internal
 *
 * A running workflow retains the definition graph and input captured when it started.
 * Node activations and signal subscriptions are stored separately in workflow_node_execution.
 */
#[Package('framework')]
#[Entity('workflow_instance', since: '6.7.15.0', collectionClass: WorkflowInstanceCollection::class)]
class WorkflowInstanceEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_instance';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    /**
     * Only the state machine may write this field; CRUD writes are rejected.
     */
    #[State(machine: WorkflowInstanceStates::STATE_MACHINE, api: true)]
    public string $stateId;

    /**
     * Readable association using the existing state_id column.
     */
    #[ManyToOne(entity: 'state_machine_state', api: true, column: 'state_id')]
    public ?StateMachineStateEntity $stateMachineState = null;

    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public ?string $result = null;

    /**
     * Unique workflow instance number.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $number;

    #[Required]
    #[ForeignKey(entity: 'workflow_execution_definition', api: true)]
    public string $definitionId;

    /**
     * Definition version at start, interpreted alongside graphSnapshot.
     */
    #[Field(type: FieldType::INT, api: true)]
    public int $definitionVersion;

    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 255)]
    public string $title;

    #[Required]
    #[ForeignKey(entity: 'user', api: true)]
    public string $initiatorId;

    /**
     * Form data snapshot used by expressions and instance details.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $formData = null;

    /**
     * Immutable graph snapshot in the nodes/edges contract shape.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $graphSnapshot = null;

    /**
     * Current node; retained at completion so the detail view can highlight the endpoint.
     */
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public ?string $currentNodeId = null;

    /**
     * Execution round used to distinguish repeated activations of the same node.
     */
    #[Field(type: FieldType::INT, api: true)]
    public int $round = 1;

    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $startedAt = null;

    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $endedAt = null;

    /**
     * Extension-owned runtime metadata.
     * Values needed for indexed queries belong in dedicated fields or entities.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;

    /**
     * @return array<string, mixed>
     */
    public function form(): array
    {
        return $this->formData ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->graphSnapshot ?? ['nodes' => [], 'edges' => []];
    }

    public function state(): string
    {
        return $this->stateMachineState?->getTechnicalName()
            ?? throw WorkflowExecutionException::stateNotLoaded();
    }

    public function isRunning(): bool
    {
        return $this->state() === WorkflowInstanceStates::RUNNING;
    }
}
