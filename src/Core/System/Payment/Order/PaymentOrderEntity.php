<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * A payment order of the generic payment gateway. An external app brings its own out trade number and
 * needs no order, the shop's own orders carry the order and the transaction they were opened for.
 * The references are what the paid event is written back to, so a checkout and a refund can find the
 * payment again.
 *
 * All amounts are integers in the smallest unit of the currency (e.g. fen for CNY).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_order', since: '6.7.18.0', collectionClass: PaymentOrderCollection::class)]
class PaymentOrderEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'payment_order';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The payment number of the platform, the identifier the app works with.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $paymentNo;

    /**
     * The trade number of the app, unique per app.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $outTradeNo;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    /**
     * The order of the checkout this payment was opened for. It stays empty for the order of an
     * external app, which is complete without the shop's order.
     */
    #[ForeignKey(entity: 'order', api: false)]
    public ?string $orderId = null;

    /**
     * The version the order reference points into. The order is versioned, so a reference into it
     * always spans the id and the version - the same way a transaction references its order.
     */
    #[ReferenceVersion(entity: 'order')]
    public ?string $orderVersionId = null;

    /**
     * The transaction of that order. The paid state is written back to it, therefore a checkout order
     * always has it while an app order never does.
     */
    #[ForeignKey(entity: 'order_transaction', api: false)]
    public ?string $orderTransactionId = null;

    #[ReferenceVersion(entity: 'order_transaction')]
    public ?string $orderTransactionVersionId = null;

    /**
     * The payment interface that is charged and holds the merchant account, e.g. `alipay`. It decides
     * whose credentials are used to query, refund or verify the payment.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    /**
     * The channel the app asked for, e.g. `alipay_h5`. It only decides how the payer was handed over
     * to the interface; everything after that runs on the interface alone.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channelTechnicalName;

    /**
     * The parameters the channel needs for this very payment, e.g. the openid of a wechat jsapi payment.
     * They are handed in by the app when the order is created.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $channelParams = null;

    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $amount;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $paidAmount = 0;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $refundedAmount = 0;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 3)]
    public string $currency;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false])]
    #[SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)]
    public string $subject;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 512)]
    public ?string $body = null;

    /**
     * Handed back unchanged in the notification to the app.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 1024)]
    public ?string $attach = null;

    #[Required]
    #[State(machine: PaymentOrderStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => false])]
    public string $stateId;

    /**
     * The trade number of the channel, e.g. the alipay trade_no.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    #[SearchRanking(SearchRanking::LOW_SEARCH_RANKING)]
    public ?string $outPaymentNo = null;

    /**
     * The raw (already sanitized) answer of the channel.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $channelData = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 45)]
    public ?string $clientIp = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public ?string $device = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 1024)]
    public ?string $notifyUrl = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 1024)]
    public ?string $returnUrl = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $idempotencyKey = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $expireAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $paidAt = null;

    /**
     * The order is not payable anymore: the app closed it, or the platform expired it because the payer
     * never showed up. Which of the two happened is told by the state of the order.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $closedAt = null;

    #[ManyToOne(entity: 'payment_app', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;

    /**
     * The state of the order, reading the very same column as the state field above.
     *
     * A state field is only a foreign key value to the dal - it does not know the table it points
     * into, so without this association the schema builder never sees the relation and every
     * `dal:migration:create` would propose to drop the foreign key that the migration created. The
     * association is the trunk way of declaring it: one field and one association on `state_id`.
     */
    #[ManyToOne(entity: 'state_machine_state', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false], column: 'state_id')]
    public ?StateMachineStateEntity $stateMachineState = null;

    /**
     * The order of the checkout, deleted together with it like its transactions - a payment of a
     * deleted order can no longer be booked anywhere.
     */
    #[ManyToOne(entity: 'order', onDelete: OnDelete::CASCADE, api: false)]
    public ?OrderEntity $order = null;

    #[ManyToOne(entity: 'order_transaction', onDelete: OnDelete::CASCADE, api: false)]
    public ?OrderTransactionEntity $orderTransaction = null;

    /**
     * The attempts to pay this order, they only exist together with the order.
     *
     * @var array<string, PaymentTransactionEntity>|null
     */
    #[OneToMany(entity: 'payment_transaction', ref: 'payment_order_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $transactions = null;

    /**
     * @var array<string, PaymentRefundEntity>|null
     */
    #[OneToMany(entity: 'payment_refund', ref: 'payment_order_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $refunds = null;

    /**
     * @var array<string, PaymentNotificationEntity>|null
     */
    #[OneToMany(entity: 'payment_notification', ref: 'payment_order_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $notifications = null;

    /**
     * @var array<string, PaymentWebhookDeliveryEntity>|null
     */
    #[OneToMany(entity: 'payment_webhook_delivery', ref: 'payment_order_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $webhookDeliveries = null;
}
