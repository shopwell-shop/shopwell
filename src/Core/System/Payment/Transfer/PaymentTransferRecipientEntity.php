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
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;

/**
 * The party a transfer is paid out to.
 *
 * A recipient is a first class citizen because the interfaces disagree on what they need before the
 * very first payout: stripe refuses to send anything without an onboarded `acct_`, wechat only knows
 * an openid of the very app, paypal accepts an email right away but asks for an account only when the
 * money is claimed. The table therefore expresses both maturities: a bare identifier (paypal) and a
 * fully onboarded account with bank details (stripe, alipay to a card).
 *
 * The identity is scoped to the app: the alipay uid of the same person is a different recipient under
 * a different app, so the unique key spans the app. The identity value is a credential and is stored
 * encrypted; the unique key therefore spans its hash instead of the value itself.
 *
 * The onboarding status is a mirror of the channel and not a state machine - see
 * PaymentTransferStates::ONBOARDING_UNREGISTERED.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_transfer_recipient', since: '6.7.18.0', collectionClass: PaymentTransferRecipientCollection::class)]
class PaymentTransferRecipientEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_transfer_recipient';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    /**
     * The interface that pays out, e.g. `alipay` or `stripe`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $provider;

    /**
     * What the identity value is, e.g. `alipay_user_id`, `wechat_open_id`, `stripe_account` or
     * `paypal_email`. It decides which field of the payout request it is written to.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $identityType;

    /**
     * The identity itself - a uid, a logon id, an openid, a card number, an account id or an email.
     *
     * Stored encrypted: a card number is a credential and never leaves the platform in plain text.
     * The cipher is not deterministic, so the column is not part of any unique key - see $identityHash.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public string $identityValue;

    /**
     * The deterministic half of the identity: `sha256(payment_app_id|provider|identity_type|identity_value)`
     * over the plain text, written by PaymentTransferRecipientService.
     *
     * It is what the unique key over (app, provider, identity type) is built on - the encrypted value
     * above can not be, because the very same plain text encrypts differently every time - and it is
     * the only index that answers "which payouts went to this identity", since an encrypted column
     * can not be searched.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $identityHash;

    /**
     * The name of the holder. Required by alipay for a logon id and for a bank card, required by
     * wechat from 2000 yuan on, not known to paypal.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $holderName = null;

    /**
     * The id the interface assigned to the account itself - stripe's `acct_`, paypal's payer id. It
     * only exists once the recipient is known to the interface.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $providerAccountId = null;

    /**
     * The bank details of a transfer to a card - alipay's `bankcard_ext_info`: whether the account is
     * a company or a private one, the name of the bank and the branch or the clearing number. Only a
     * transfer to a card needs them.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $bankInfo = null;

    /**
     * How far the recipient is onboarded on the channel side - one of the
     * PaymentTransferStates::ONBOARDING_* values.
     *
     * Not nullable and carries no ddl default: the dal never writes the property initial value and
     * ignores it for the insert, so every writer has to spell `onboardingStatus => 'unregistered'`
     * out, exactly like PaymentOrderService::create() does for paidAmount.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $onboardingStatus;

    /**
     * The mirror of stripe's `payouts_enabled`. Every other interface pays out to any recipient it
     * accepted, so the column is only ever false for stripe.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $payoutEnabled = false;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $verifiedAt = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $metadata = null;

    #[ManyToOne(entity: 'payment_app', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;

    /**
     * The transfers that were paid out to this recipient.
     *
     * @var array<string, PaymentTransferEntity>|null
     */
    #[OneToMany(entity: 'payment_transfer', ref: 'recipient_id', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $transfers = null;
}
