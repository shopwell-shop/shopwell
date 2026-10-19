<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * A subscription instance: one customer paying one plan again and again.
 *
 * The billing mode is redundant on the instance on purpose - it decides who schedules and who
 * charges, and it must not change under a running subscription even if the plan is edited. A
 * channel managed subscription is located by its provider subscription id, a merchant initiated one
 * by the mandate it charges through.
 *
 * The retry bookkeeping (failure count, next retry) is only maintained by the platform; a channel
 * managed subscription only mirrors what the channel reports.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_subscription', since: '6.7.18.0', collectionClass: PaymentSubscriptionCollection::class)]
class PaymentSubscriptionEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_subscription';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The subscription number of the platform, the identifier the customer sees.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $subscriptionNo;

    #[Required]
    #[ForeignKey(entity: 'payment_plan', api: false)]
    public string $planId;

    #[Required]
    #[ForeignKey(entity: 'customer', api: false)]
    public string $customerId;

    #[Required]
    #[ForeignKey(entity: 'sales_channel', api: false)]
    public string $salesChannelId;

    /**
     * Redundant from the plan and immutable once written: it decides who schedules the periods and
     * who initiates the charges - see PaymentPlanEntity for the two modes.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $billingMode;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $provider;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channel;

    /**
     * The subscription object on the channel side - the stripe `sub_*`, the paypal `I-*`. It only
     * exists for a channel managed subscription.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $providerSubscriptionId = null;

    /**
     * The agreement that is charged - only a merchant initiated subscription has one.
     */
    #[ForeignKey(entity: 'payment_mandate', api: false)]
    public ?string $mandateId = null;

    #[Required]
    #[State(machine: PaymentSubscriptionStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => false])]
    public string $statusId;

    /**
     * The raw status the channel reports, kept for debugging the mapping onto the unified status.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $providerStatus = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $currentPeriodStart = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $currentPeriodEnd = null;

    /**
     * The anchor the next period is scheduled from - only a merchant initiated subscription books
     * its own periods, a channel managed one is driven by the webhooks of the channel.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $nextBillingAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $trialEnd = null;

    /**
     * The moment the subscription is scheduled to cancel itself.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $cancelAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $canceledAt = null;

    /**
     * The moment the subscription reached a final state - canceled or expired.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $endedAt = null;

    /**
     * How many periods in a row failed - the platform counts them for its own retry policy. Not
     * nullable and carried no ddl default: the dal never writes the property initial value, so
     * every writer has to spell `failureCount => 0` out, exactly like PaymentOrderService::create()
     * does for paidAmount and refundedAmount.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $failureCount = 0;

    /**
     * The raw failure code of the last failed charge.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $lastFailureCode = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $nextRetryAt = null;

    #[ManyToOne(entity: 'payment_plan', onDelete: OnDelete::RESTRICT, api: false)]
    public ?PaymentPlanEntity $plan = null;

    #[ManyToOne(entity: 'payment_mandate', onDelete: OnDelete::RESTRICT, api: false)]
    public ?PaymentMandateEntity $mandate = null;

    /**
     * The customer the subscription belongs to. Restricted, like the currency and the sales channel
     * of a checkout order - a subscription of a deleted customer would keep billing an account that
     * no longer exists.
     */
    #[ManyToOne(entity: 'customer', onDelete: OnDelete::RESTRICT, api: false)]
    public ?CustomerEntity $customer = null;

    #[ManyToOne(entity: 'sales_channel', onDelete: OnDelete::RESTRICT, api: false)]
    public ?SalesChannelEntity $salesChannel = null;

    /**
     * The status of the subscription, reading the very same column as the status field above.
     *
     * A state field is only a foreign key value to the dal - it does not know the table it points
     * into, so without this association the schema builder never sees the relation and every
     * `dal:migration:create` would propose to drop the foreign key that the migration created. The
     * association is the trunk way of declaring it: one field and one association on `status_id`.
     */
    #[ManyToOne(entity: 'state_machine_state', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false], column: 'status_id')]
    public ?StateMachineStateEntity $statusMachineState = null;
}
