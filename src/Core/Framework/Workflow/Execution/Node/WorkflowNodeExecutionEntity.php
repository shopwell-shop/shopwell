<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Node;

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
 */
#[Package('framework')]
#[Entity('workflow_node_execution', since: '6.7.15.0', collectionClass: WorkflowNodeExecutionCollection::class)]
class WorkflowNodeExecutionEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_node_execution';
    public const RUNNING = 'running';
    public const WAITING = 'waiting';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance')]
    public string $instanceId;

    #[Required]
    #[Field(type: FieldType::STRING, maxLength: 64)]
    public string $nodeId;

    #[Required]
    #[Field(type: FieldType::INT)]
    public int $round;

    #[Required]
    #[Field(type: FieldType::INT)]
    public int $sequence;

    #[Field(type: FieldType::STRING, maxLength: 64)]
    public ?string $sourceConnectionId = null;

    #[Required]
    #[Field(type: FieldType::STRING, maxLength: 32)]
    public string $status;

    #[Field(type: FieldType::STRING, maxLength: 255)]
    public ?string $waitingFor = null;

    #[Field(type: FieldType::STRING, maxLength: 64)]
    public ?string $result = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON)]
    public ?array $output = null;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeImmutable $startedAt = null;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeImmutable $endedAt = null;
}
