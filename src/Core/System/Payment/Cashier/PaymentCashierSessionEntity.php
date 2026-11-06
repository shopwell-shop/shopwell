<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Cashier;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_cashier_session', since: '6.7.18.0', collectionClass: PaymentCashierSessionCollection::class)]
class PaymentCashierSessionEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: false)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: false)]
    public string $paymentAppId;

    #[Required]
    #[ForeignKey(entity: 'payment_cashier_config', api: false)]
    public string $cashierConfigId;

    #[Required]
    #[Field(type: FieldType::STRING, api: false, maxLength: 128)]
    public string $outTradeNo;

    /**
     * @var array<string, mixed>
     */
    #[Required]
    #[Field(type: FieldType::JSON, api: false)]
    public array $payload;

    /**
     * @var array<string, mixed>
     */
    #[Required]
    #[Field(type: FieldType::JSON, api: false)]
    public array $paymentContext;

    /**
     * @var list<string>
     */
    #[Field(type: FieldType::JSON, api: false)]
    public array $allowedMethods = [];

    #[Required]
    #[Field(type: FieldType::STRING, api: false, maxLength: 64)]
    public string $routingPolicy;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $presentation = null;

    #[Required]
    #[Field(type: FieldType::DATETIME, api: false)]
    public \DateTimeInterface $expireAt;

    #[Field(type: FieldType::STRING, api: false, maxLength: 64)]
    public ?string $selectedMethod = null;

    #[ForeignKey(entity: 'payment_order', api: false)]
    public ?string $paymentOrderId = null;
}
