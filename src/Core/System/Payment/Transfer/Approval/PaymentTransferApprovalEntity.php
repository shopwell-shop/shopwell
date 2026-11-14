<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal Immutable evidence binding one transfer to one workflow execution.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_transfer_approval', since: '6.7.18.0', collectionClass: PaymentTransferApprovalCollection::class)]
class PaymentTransferApprovalEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: false)]
    public string $id;

    #[Required]
    #[Field(type: FieldType::UUID, api: false)]
    public string $releaseId;

    #[Required]
    #[Field(type: FieldType::UUID, api: false)]
    public string $instanceId;

    #[Required]
    #[Field(type: FieldType::UUID, api: false)]
    public string $initiatorId;

    /**
     * @var array<string, mixed>
     */
    #[Required]
    #[Field(type: FieldType::JSON, api: false)]
    public array $snapshot;
}
