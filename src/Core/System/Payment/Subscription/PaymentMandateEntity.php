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
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * A signing agreement of the mandate-driven providers (alipay, wechat): the permission to charge a
 * customer again and again without them being present.
 *
 * The agreement number of the platform (`PA` prefixed) and the one handed to the channel are both
 * unique - the out agreement number of wechat can never be reused, not even after an unsigned
 * agreement, so a second signing always carries a fresh one. The limits are a local copy of what
 * the channel side allows, so a charge that would violate them is detected before it is submitted.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_mandate', since: '6.7.18.0', collectionClass: PaymentMandateCollection::class)]
class PaymentMandateEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_mandate';

    final public const TEMPLATE_TYPE_NO_PASSWORD = 'no_password';
    final public const TEMPLATE_TYPE_AUTO_RENEW = 'auto_renew';
    final public const TEMPLATE_TYPE_SCHEDULED = 'scheduled';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The agreement number of the platform, the identifier the subscription domain works with.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $agreementNo;

    /**
     * The agreement number handed to the channel - alipay's `external_agreement_no`, wechat's
     * `out_contract_code`. Alipay only knows it when it was passed at signing time, wechat always.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $outAgreementNo = null;

    /**
     * The agreement number the channel side reports back - alipay's `agreement_no`, wechat's
     * `contract_id`. It only exists once the customer signed.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $providerAgreementNo = null;

    #[Required]
    #[ForeignKey(entity: 'customer', api: false)]
    public string $customerId;

    #[Required]
    #[ForeignKey(entity: 'payment_plan', api: false)]
    public string $planId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $provider;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channel;

    /**
     * The signing scene of alipay, e.g. `CYCLE_PAY_AUTH_P` - only known to alipay.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $signScene = null;

    /**
     * The product code of alipay, e.g. `CYCLE_PAY_AUTH_P` - only known to alipay.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $personalProductCode = null;

    /**
     * The terms exactly as they were signed - period, single limit, total times, trial. The channel
     * may change its template any time, the mandate keeps executing what was agreed.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $termsSnapshot = null;

    #[Required]
    #[State(machine: PaymentMandateStates::STATE_MACHINE, api: ['admin-api' => true, 'store-api' => false])]
    public string $statusId;

    /**
     * The per charge limit the channel side allows, in the smallest unit of the currency.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $singleLimit = null;

    /**
     * The total amount limit the channel side allows, in the smallest unit of the currency.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $totalLimit = null;

    /**
     * The total number of charges the channel side allows.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $maxTimes = null;

    /**
     * The kind of template the agreement was signed on: a `no_password` agreement, an `auto_renew`
     * agreement (what subscriptions use) or a `scheduled` one, which is spent with one charge.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public ?string $templateType = null;

    /**
     * Whether the agreement may be charged again and again. An `auto_renew` agreement is reusable, a
     * `scheduled` one dies with its single successful charge.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $reusable = false;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $signedAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $expiresAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $terminatedAt = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $terminationReason = null;

    #[ManyToOne(entity: 'payment_plan', onDelete: OnDelete::RESTRICT, api: false)]
    public ?PaymentPlanEntity $plan = null;

    /**
     * The customer who signed the agreement - restricted, because an agreement of a deleted customer
     * would keep permitting charges against an account that no longer exists.
     */
    #[ManyToOne(entity: 'customer', onDelete: OnDelete::RESTRICT, api: false)]
    public ?CustomerEntity $customer = null;

    /**
     * The state of the agreement, reading the very same column as the status field above.
     *
     * A state field is only a foreign key value to the dal - it does not know the table it points
     * into, so without this association the schema builder never sees the relation and every
     * `dal:migration:create` would propose to drop the foreign key that the migration created. The
     * association is the trunk way of declaring it: one field and one association on `status_id`.
     */
    #[ManyToOne(entity: 'state_machine_state', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false], column: 'status_id')]
    public ?StateMachineStateEntity $statusMachineState = null;
}
