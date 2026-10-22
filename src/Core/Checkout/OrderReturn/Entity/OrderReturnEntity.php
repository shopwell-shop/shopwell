<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage\OrderReturnMessageEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnReason\OrderReturnReasonEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
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
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\User\UserEntity;

/**
 * A customer sending goods of an order back, with or without asking for the money.
 *
 * It is the counter part of the order: an order is a promise to deliver, a return is the withdrawal
 * of that promise for the positions it names. The reference to the order is therefore versioned
 * exactly like the one of a transaction, and the positions point into the line items of that order
 * instead of copying them - whatever the order said about a position stays the truth.
 *
 * The money is not part of this entity. A refund is booked on the payment gateway
 * (`payment_refund`), the return only remembers which refund paid it out. That is the split shopify
 * makes as well: the return is the goods, the refund is the money, and a refund may exist without a
 * return while a return always ends in at most one of them.
 *
 * All amounts are integers in the smallest unit of the currency (e.g. fen for CNY), the same way the
 * payment gateway books them.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[Entity('order_return', since: '6.7.18.0', collectionClass: OrderReturnCollection::class)]
class OrderReturnEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'order_return';

    /**
     * Who wrote a message of the return, and who is allowed to read it.
     */
    final public const AUTHOR_CUSTOMER = 'customer';
    final public const AUTHOR_AGENT = 'agent';
    final public const AUTHOR_SYSTEM = 'system';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The number a customer, a parcel and a support agent quote. It is unique and never reused.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 64)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $returnNumber;

    #[Required]
    #[ForeignKey(entity: 'order', api: ['admin-api' => true, 'store-api' => false])]
    public string $orderId;

    /**
     * The order is versioned, so a reference into it always spans the id and the version.
     */
    #[ReferenceVersion(entity: 'order')]
    public string $orderVersionId;

    /**
     * Empty for a guest return, which is tied to the email address of the order instead.
     */
    #[ForeignKey(entity: 'customer', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $customerId = null;

    /**
     * The address a guest return belongs to. Kept for guests only, a customer return is found
     * through the customer and never through the mail address.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $guestEmail = null;

    /**
     * The hash of the token a guest got when the order was verified. The token itself is never
     * stored - losing the table would otherwise hand out every guest return of the shop.
     */
    #[Field(type: FieldType::STRING, api: false, maxLength: 128)]
    public ?string $guestTokenHash = null;

    #[Required]
    #[ForeignKey(entity: 'sales_channel', api: ['admin-api' => true, 'store-api' => false])]
    public string $salesChannelId;

    /**
     * One of {@see \Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes}. The core knows the two money
     * back resolutions, every other one is added by a plugin.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 32)]
    public string $type;

    /**
     * The reason the customer picked from the maintained list.
     */
    #[ForeignKey(entity: 'order_return_reason', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $reasonId = null;

    /**
     * The free text of the customer, kept even when a reason was picked.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 1024)]
    public ?string $reasonText = null;

    #[Field(type: FieldType::TEXT, api: ['admin-api' => true, 'store-api' => true])]
    public ?string $customerNote = null;

    /**
     * The uploaded evidence, a list of media ids or urls as handed in by the storefront.
     *
     * @var list<string>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $evidence = null;

    /**
     * The amount that goes back, the sum of the positions. It is booked once and never edited, a
     * wrong amount is a new return.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => true])]
    public int $refundAmount = 0;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 3)]
    public string $currency;

    #[Required]
    #[State(machine: OrderReturnStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => true])]
    public string $stateId;

    /**
     * The refund of the payment gateway this return was paid out with. The refund itself lives in
     * `payment_refund`, this is the reference a merchant quotes to the gateway support.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $paymentRefundNo = null;

    /**
     * The administrator who decided on the return last.
     */
    #[ForeignKey(entity: 'user', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $administratorId = null;

    /**
     * The moment the current state has to be left, otherwise the configured automatic action runs.
     * Null means the state has no deadline.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => true])]
    public ?\DateTimeInterface $deadlineAt = null;

    /**
     * When the customer announced the return. Kept although the state tells that the parcel is on its
     * way, because a merchant needs the date and not only the fact.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => true])]
    public ?\DateTimeInterface $returnedAt = null;

    /**
     * When the goods arrived at the shop.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => true])]
    public ?\DateTimeInterface $receivedAt = null;

    /**
     * The carrier tracking number the customer typed in.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 128)]
    public ?string $trackingCode = null;

    /**
     * The carrier the parcel travels with, as named by the customer or by the shop.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 64)]
    public ?string $trackingCarrier = null;

    /**
     * The state of the return, reading the very same column as the state field above.
     *
     * A state field is only a foreign key value to the dal, so without this association the schema
     * builder never sees the relation and would propose to drop the foreign key.
     */
    #[ManyToOne(entity: 'state_machine_state', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => true], column: 'state_id')]
    public ?StateMachineStateEntity $stateMachineState = null;

    /**
     * The order the return belongs to. Like on the line item the cascade is declared on the reverse
     * side, the dal forbids a delete flag on a many to one association - the children are deleted by
     * the parent, so deleting the order still takes its returns with it.
     */
    #[ManyToOne(entity: 'order', api: false)]
    public ?OrderEntity $order = null;

    #[ManyToOne(entity: 'customer', api: false)]
    public ?CustomerEntity $customer = null;

    #[ManyToOne(entity: 'sales_channel', api: false)]
    public ?SalesChannelEntity $salesChannel = null;

    #[ManyToOne(entity: 'order_return_reason', onDelete: OnDelete::SET_NULL, api: ['admin-api' => true, 'store-api' => false])]
    public ?OrderReturnReasonEntity $reason = null;

    /**
     * The administrator who decided on the return last, kept out of the store api.
     */
    #[ManyToOne(entity: 'user', onDelete: OnDelete::SET_NULL, api: false)]
    public ?UserEntity $administrator = null;

    /**
     * The positions of the return, they only exist together with it.
     *
     * @var array<string, OrderReturnLineItemEntity>|null
     */
    #[OneToMany(entity: 'order_return_line_item', ref: 'order_return_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $lineItems = null;

    /**
     * @var array<string, OrderReturnMessageEntity>|null
     */
    #[OneToMany(entity: 'order_return_message', ref: 'order_return_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $messages = null;
}
