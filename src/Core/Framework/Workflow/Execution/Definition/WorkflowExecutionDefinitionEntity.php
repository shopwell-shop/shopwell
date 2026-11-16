<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Definition;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Persisted executable graph with its publication status and version.
 * Instances capture their own graph snapshot so later definition changes do not affect execution.
 */
#[Package('framework')]
#[Entity('workflow_execution_definition', since: '6.7.15.0', collectionClass: WorkflowExecutionDefinitionCollection::class)]
class WorkflowExecutionDefinitionEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_execution_definition';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    /**
     * Business code, such as expense; only one version per code is latest.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $code;

    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 255)]
    public string $name;

    #[Field(type: FieldType::TEXT, api: true)]
    public ?string $description = null;

    /**
     * One WorkflowExecutionDefinitionStatus value; only system-scope service operations may update it.
     */
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $status = WorkflowExecutionDefinitionStatus::DRAFT;

    /**
     * Version number starting at one; reserved for future definition versioning.
     */
    #[Field(type: FieldType::INT, api: true)]
    public int $version = 1;

    /**
     * Whether this is the latest version for its code.
     */
    #[Field(type: FieldType::BOOL, api: true)]
    public bool $isLatest = true;

    /**
     * The graph emitted by the designer.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $graphData = null;

    /**
     * Optional workflow input fields, also used to validate expression variables.
     *
     * @var list<array<string, mixed>>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $formSchema = null;

    /**
     * Extension-owned definition metadata.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;

    /**
     * @return array<string, mixed>
     */
    public function graph(): array
    {
        return $this->graphData ?? ['nodes' => [], 'edges' => []];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formFields(): array
    {
        return $this->formSchema ?? [];
    }
}
