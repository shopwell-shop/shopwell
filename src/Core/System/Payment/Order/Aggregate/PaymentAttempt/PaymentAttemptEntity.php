<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * One resolved execution of a fixed payment obligation.
 * The route, request identity and non-secret configuration snapshot never change after claiming it.
 * Pending and unknown executions must be reconciled before another route can be attempted.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_attempt', since: '6.7.18.0', collectionClass: PaymentAttemptCollection::class)]
class PaymentAttemptEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_attempt';

    final public const STATUS_PENDING = 'pending';
    final public const STATUS_HANDOVER = 'handover';
    final public const STATUS_UNKNOWN = 'unknown';
    final public const STATUS_PAID = 'paid';
    final public const STATUS_CLOSED = 'closed';
    final public const STATUS_FAILED = 'failed';

    #[PrimaryKey]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[ForeignKey(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentOrderId;

    /**
     * The stable merchant request identity sent to the provider.
     */
    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $attemptNo;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[ForeignKey(entity: 'payment_account', api: false)]
    public string $accountId;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $method;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channelTechnicalName;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $interaction;

    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $status;

    /**
     * @var array<string, mixed>
     */
    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::JSON, api: false)]
    public array $context = [];

    /**
     * @var array<string, mixed>
     */
    #[Required]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::JSON, api: false)]
    public array $configuration = [];

    /**
     * @var array<string, mixed>|null
     */
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $handover = null;

    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $outPaymentNo = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $channelData = null;

    #[ManyToOne(entity: 'payment_account', api: false)]
    public ?PaymentAccountEntity $account = null;

    #[ManyToOne(entity: 'payment_order', api: false)]
    public ?PaymentOrderEntity $paymentOrder = null;

    #[OneToMany(entity: 'payment_order', ref: 'active_attempt_id', api: false)]
    public ?PaymentOrderCollection $activePaymentOrders = null;

    #[OneToMany(entity: 'payment_transaction', ref: 'payment_attempt_id', api: false)]
    public ?PaymentTransactionCollection $transactions = null;

    #[OneToMany(entity: 'payment_refund', ref: 'payment_attempt_id', api: false)]
    public ?PaymentRefundCollection $refunds = null;

    #[OneToMany(entity: 'payment_notification', ref: 'payment_attempt_id', api: false)]
    public ?PaymentNotificationCollection $notifications = null;
}
