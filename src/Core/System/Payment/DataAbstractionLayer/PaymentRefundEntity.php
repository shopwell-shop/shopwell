<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentRefundStates;

/**
 * A refund of a payment order, partial refunds are supported.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_refund', since: '6.7.18.0', collectionClass: PaymentRefundCollection::class)]
class PaymentRefundEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'payment_refund';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $refundNo;

    #[Required]
    #[ForeignKey(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentOrderId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $outRefundNo;

    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $amount;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 3)]
    public string $currency;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $reason = null;

    #[Required]
    #[State(machine: PaymentRefundStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => false])]
    public string $stateId;

    /**
     * The refund number of the channel.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $outRefundId = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $refundedAt = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $requestPayload = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $responsePayload = null;

    /**
     * The refund belongs to its order, the cascade is declared on the reverse side in
     * PaymentOrderEntity, so the many to one side stays without a delete flag.
     */
    #[ManyToOne(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentOrderEntity $paymentOrder = null;
}
