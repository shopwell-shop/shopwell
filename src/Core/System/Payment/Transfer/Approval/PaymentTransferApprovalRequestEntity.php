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
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_transfer_approval_request', since: '6.7.18.0', collectionClass: PaymentTransferApprovalRequestCollection::class)]
class PaymentTransferApprovalRequestEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: false)]
    public string $id;

    #[Required]
    #[Field(type: FieldType::UUID, api: false)]
    public string $requestId;

    /**
     * @var array{previousTransfer?: array{id: string, transferNo: string}, ...<string, mixed>}
     */
    #[Required]
    #[Field(type: FieldType::JSON, api: false)]
    public array $snapshot;
}
