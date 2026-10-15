<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Webhook;

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
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * One delivery of an outgoing notification to the app, including the retries.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_webhook_delivery', since: '6.7.18.0', collectionClass: PaymentWebhookDeliveryCollection::class)]
class PaymentWebhookDeliveryEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const STATUS_PENDING = 'pending';
    final public const STATUS_SUCCESS = 'success';
    final public const STATUS_FAILED = 'failed';
    final public const STATUS_GIVING_UP = 'giving_up';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentOrderId;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $event;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 1024)]
    public string $url;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $payload = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $signature = null;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $status = self::STATUS_PENDING;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $attemptCount = 0;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $nextRetryAt = null;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $lastHttpStatus = null;

    #[Field(type: FieldType::TEXT, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $lastResponse = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $succeededAt = null;

    /**
     * A delivery is only kept as long as its order and its app exist, the cascades are declared on
     * the reverse sides (PaymentOrderEntity and PaymentAppEntity).
     */
    #[ManyToOne(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentOrderEntity $paymentOrder = null;

    #[ManyToOne(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;
}
