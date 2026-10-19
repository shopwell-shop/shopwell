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
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * One period of one subscription - one charge attempt per period, the subscription billing
 * equivalent of a payment order. A period is billed exactly once: the unique key over the
 * subscription and the cycle number is what keeps the scheduler and the webhooks from booking a
 * second charge for the same period.
 *
 * The out trade number is the idempotency key towards the channel and is reused by every retry -
 * a second number would be a second charge, never a retry.
 *
 * All amounts are integers in the smallest unit of the currency (e.g. fen for CNY).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_subscription_cycle', since: '6.7.18.0', collectionClass: PaymentSubscriptionCycleCollection::class)]
class PaymentSubscriptionCycleEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_subscription_cycle';

    final public const FAILURE_CLASS_RETRYABLE = 'retryable';
    final public const FAILURE_CLASS_FINAL = 'final';
    final public const FAILURE_CLASS_UNKNOWN = 'unknown';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_subscription', api: false)]
    public string $subscriptionId;

    /**
     * The number of the period within the subscription, starting at 1. Deriving it from the events
     * of the channel is the only hard part of a channel managed subscription - paypal has no invoice
     * per period and the number has to be reconstructed from its transaction list.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $cycleNo;

    #[Required]
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public \DateTimeInterface $periodStart;

    #[Required]
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public \DateTimeInterface $periodEnd;

    /**
     * The amount charged for this period.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $amount;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 3)]
    public string $currency;

    #[Required]
    #[State(machine: PaymentSubscriptionCycleStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => false])]
    public string $stateId;

    /**
     * The trade number handed to the channel, unique and reused by every retry of this cycle.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public string $outTradeNo;

    /**
     * The invoice the channel opened for this period - the stripe `in_*`. Paypal has none.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $providerInvoiceId = null;

    /**
     * The transaction the charge succeeded with.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $providerTransactionId = null;

    /**
     * The raw failure code of the channel, e.g. `ACQ.BUYER_BALANCE_NOT_ENOUGH`.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $failureCode = null;

    /**
     * The normalized classification of the failure - retryable, final or unknown.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public ?string $failureClass = null;

    /**
     * The number of retries already scheduled; the total number of charge attempts of a cycle is
     * therefore `attemptCount + 1` once it has failed.
     *
     * Not nullable and carries no ddl default: the dal never writes the property initial value, so
     * every writer has to spell `attemptCount => 0` out, exactly like
     * PaymentOrderService::create() does for paidAmount and refundedAmount.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $attemptCount = 0;

    /**
     * When the next retry may be submitted - only a retryable failure books one.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $nextRetryAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $paidAt = null;

    #[ManyToOne(entity: 'payment_subscription', onDelete: OnDelete::RESTRICT, api: false)]
    public ?PaymentSubscriptionEntity $subscription = null;

    /**
     * The state of the cycle, reading the very same column as the state field above.
     *
     * A state field is only a foreign key value to the dal - it does not know the table it points
     * into, so without this association the schema builder never sees the relation and every
     * `dal:migration:create` would propose to drop the foreign key that the migration created. The
     * association is the trunk way of declaring it: one field and one association on `state_id`.
     */
    #[ManyToOne(entity: 'state_machine_state', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false], column: 'state_id')]
    public ?StateMachineStateEntity $stateMachineState = null;
}
