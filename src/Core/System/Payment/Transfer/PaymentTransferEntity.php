<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * One payout of the platform to one recipient - one order equals one recipient equals one request to
 * the interface. Batches are deliberately not modelled: paypal is the only interface that insists on
 * a batch and sending one item per request is legal there, while a batch would bring a second state
 * machine, a second idempotency key and a third level of status.
 *
 * The domain is separated from `payment_order` and does not reuse it: the paid and refunded amounts of
 * an order are about collecting money, and giving them a second meaning in the opposite direction
 * would break every reconciliation.
 *
 * All amounts are integers in the smallest unit of the currency (e.g. fen for CNY) - the interfaces
 * convert at their own boundary only.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_transfer', since: '6.7.18.0', collectionClass: PaymentTransferCollection::class)]
class PaymentTransferEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_transfer';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    /**
     * The transfer number of the platform, the identifier the administration works with.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $transferNo;

    /**
     * The idempotency key of the business case, unique per app. It is reused by every retry - a second
     * number would be a second payout, never a retry.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $outNo;

    #[Required]
    #[ForeignKey(entity: 'payment_transfer_recipient', api: ['admin-api' => true, 'store-api' => false])]
    public string $recipientId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $provider;

    /**
     * The channel the transfer is declared with - it decides the flow and the parameters that are
     * validated before the request is built.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channel;

    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $amount;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 3)]
    public string $currency;

    #[Required]
    #[State(machine: PaymentTransferStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => false])]
    public string $statusId;

    /**
     * The object the interface hands out and the query is made for - alipay's `order_id` or
     * `pay_fund_order_id`, wechat's `transfer_bill_no`, stripe's `po_`, paypal's `payout_item_id`.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $providerRef = null;

    /**
     * The reference of the first of the two stripe stages - the `tr_` of the transfer into the
     * connected account. No other interface has a stage before the payout.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $providerStageRef = null;

    /**
     * The `package_info` wechat hands out for the shopper to accept the transfer. It is a single use
     * value and the only carrier of the confirmation flow - losing it means canceling and sending
     * again.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $confirmPayload = null;

    /**
     * The failure code of the interface, stored as it came - never translated.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $failureCode = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $failureReason = null;

    /**
     * How many times this transfer was submitted to the interface. Together with the out number it
     * is the "query before sending" bookkeeping: a retry always queries first.
     *
     * Not nullable and carries no ddl default: the dal never writes the property initial value, so
     * every writer has to spell `submitAttempts => 0` out, exactly like PaymentOrderService::create()
     * does for paidAmount and refundedAmount.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $submitAttempts = 0;

    /**
     * When an unconfirmed wechat transfer is closed by the interface - 24 hours after the submission.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $expireAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $submittedAt = null;

    /**
     * When the money reached the recipient.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $settledAt = null;

    /**
     * When the money came back - alipay's `REFUND`, paypal's `RETURNED`.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $returnedAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $canceledAt = null;

    /**
     * The parameters the payout is built with, exactly as the caller filed them - alipay's
     * `transfer_scene_name` and `transfer_scene_report_infos`, for instance.
     *
     * They are stored instead of only being handed to the adapter because the submission may happen
     * long after the transfer was created and may be repeated days later by a task that has no caller
     * to ask. Not part of any api: they are the input of the submission, not administration data.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $channelParams = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $metadata = null;

    #[ManyToOne(entity: 'payment_app', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;

    #[ManyToOne(entity: 'payment_transfer_recipient', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentTransferRecipientEntity $recipient = null;

    /**
     * The status of the transfer, reading the very same column as the status field above.
     *
     * A state field is only a foreign key value to the dal - it does not know the table it points
     * into, so without this association the schema builder never sees the relation and every
     * `dal:migration:create` would propose to drop the foreign key that the migration created. The
     * association is the trunk way of declaring it: one field and one association on `status_id`.
     */
    #[ManyToOne(entity: 'state_machine_state', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false], column: 'status_id')]
    public ?StateMachineStateEntity $statusMachineState = null;

    /**
     * The notifications the app sent for this transfer, they only exist together with it.
     *
     * @var array<string, PaymentNotificationEntity>|null
     */
    #[OneToMany(entity: 'payment_notification', ref: 'payment_transfer_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $notifications = null;
}
