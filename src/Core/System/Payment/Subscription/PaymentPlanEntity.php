<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * A subscription plan: the price and the cadence a subscription instance is billed with.
 *
 * The plan is configuration, not bookkeeping - the instances and their cycles live on
 * {@see PaymentSubscriptionEntity} and {@see PaymentSubscriptionCycleEntity}. The provider plan ref
 * is the key the channel side knows the plan by (the wechat plan id, the stripe price id, the paypal
 * plan id); alipay carries none, because its period rules are fixed at signing time and live in the
 * terms snapshot of the mandate.
 *
 * All amounts are integers in the smallest unit of the currency (e.g. fen for CNY).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_plan', since: '6.7.18.0', collectionClass: PaymentPlanCollection::class)]
class PaymentPlanEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_plan';

    /**
     * The platform holds the period calendar and charges itself (alipay, wechat).
     */
    final public const BILLING_MODE_MERCHANT_INITIATED = 'merchant_initiated';

    /**
     * The channel schedules and charges, the platform only mirrors (stripe, paypal).
     */
    final public const BILLING_MODE_CHANNEL_MANAGED = 'channel_managed';

    final public const INTERVAL_UNIT_DAY = 'day';
    final public const INTERVAL_UNIT_WEEK = 'week';
    final public const INTERVAL_UNIT_MONTH = 'month';
    final public const INTERVAL_UNIT_YEAR = 'year';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $technicalName;

    /**
     * The sales channel the plan is offered on.
     */
    #[Required]
    #[ForeignKey(entity: 'sales_channel', api: false)]
    public string $salesChannelId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $billingMode;

    /**
     * The payment interface that is charged, e.g. `alipay`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $provider;

    /**
     * The channel the subscription was signed through, e.g. `alipay_h5`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channel;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 16)]
    public string $intervalUnit;

    /**
     * The number of interval units one period spans.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $intervalCount;

    /**
     * The amount of one period.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $amount;

    #[Required]
    #[ForeignKey(entity: 'currency', api: false)]
    public string $currencyId;

    /**
     * The length of the trial in days, 0 for none.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $trialDays;

    /**
     * The key the channel side knows the plan by, e.g. the stripe price id. Alipay has none.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $providerPlanRef = null;

    /**
     * The terms of the channel side, exactly as they were when the plan was registered - the wechat
     * template limits, the stripe price, the paypal billing cycles. The channel may change them at
     * any time, the plan is what was signed up for.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $providerPlanSnapshot = null;

    #[ManyToOne(entity: 'sales_channel', onDelete: OnDelete::RESTRICT, api: false)]
    public ?SalesChannelEntity $salesChannel = null;

    /**
     * The currency the plan bills in - the same column as the currency field above. Money is an
     * integer in the smallest unit, but the plan still points at the currency it is denominated in.
     */
    #[ManyToOne(entity: 'currency', onDelete: OnDelete::RESTRICT, api: false)]
    public ?CurrencyEntity $currency = null;
}
