<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentOrderStates;

/**
 * A payment order of the generic payment gateway. It is the source of truth of one payment and is
 * completely independent of an order, the app brings its own out trade number.
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
