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
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;

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
     * 业务编码（如 `expense`），同一编码只应有一个 latest 版本
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
     * {@see WorkflowExecutionDefinitionStatus} 之一。
     * 写保护到 system scope：设计器保存草稿走 WorkflowService（它会临时开 system scope），
     * 而 `PATCH /api/workflow_execution_definition/{id}` 改不动它。
     */
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $status = WorkflowExecutionDefinitionStatus::DRAFT;

    /**
     * 版本号，从 1 开始；改图要先回到草稿并升版本（当前版本未开放，字段先留）
     */
    #[Field(type: FieldType::INT, api: true)]
    public int $version = 1;

    /**
     * 是否为当前版本；同一 code 下有且仅有一行为 true
     */
    #[Field(type: FieldType::BOOL, api: true)]
    public bool $isLatest = true;

    /**
     * 设计器输出的契约图。
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
