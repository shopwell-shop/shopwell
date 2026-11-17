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
 * Copy receipts support recipient and unread filtering; copying creates no task and never blocks execution.
 *
 * @internal
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
     * The node where the copy was created.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $nodeId;

    #[Required]
    #[Field(type: FieldType::INT, api: true)]
    public int $round = 1;

    #[Required]
    #[ForeignKey(entity: 'user', api: true)]
    public string $userId;

    /**
     * Null means unread.
     */
    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $readAt = null;

    /**
     * Reserved delivery-channel and required-reading metadata.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;
}
