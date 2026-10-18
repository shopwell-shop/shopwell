<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * One attempt to pay a payment order. A payment order can be attempted more than once, each attempt
 * keeps the request and the answer of the channel for support and reconciliation.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_transaction', since: '6.7.18.0', collectionClass: PaymentTransactionCollection::class)]
class PaymentTransactionEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const TYPE_CAPTURE = 'capture';

    final public const STATUS_PENDING = 'pending';
    final public const STATUS_SUCCESS = 'success';
    final public const STATUS_FAILED = 'failed';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentOrderId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $transactionNo;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $type;

    /**
     * The payment interface the attempt was made with, e.g. `alipay`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    /**
     * The channel of the attempt, e.g. `alipay_h5`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channelTechnicalName;

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

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $outTransactionId = null;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $status;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $errorCode = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 512)]
    public ?string $errorMessage = null;

    /**
     * The attempt belongs to its order, the cascade is declared on the reverse side in
     * PaymentOrderEntity, so the many to one side stays without a delete flag.
     */
    #[ManyToOne(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentOrderEntity $paymentOrder = null;
}
