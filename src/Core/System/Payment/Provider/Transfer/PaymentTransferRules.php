<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Transfer;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a payment interface demands before it pays money out to a recipient.
 *
 * The four interfaces disagree on almost everything around payouts, and until now that disagreement
 * was written down as a matrix of provider names inside
 * `PaymentTransferRecipientService` / `PaymentTransferService`. A third party interface had no way to
 * join that matrix, so the rules move next to the declaration of the interface:
 *
 *  - `identityTypes` - which handles the interface can pay to at all. A wechat openid only exists
 *    inside the app that issued it, stripe pays out to a connected account and not to a card, alipay
 *    knows a uid, a logon id, an open id and a card.
 *  - `requiredRecipientFields` - the recipient fields the interface insists on for one of those
 *    handles, e.g. the name of the holder for an alipay logon id.
 *  - `bankAccountTypes` - the `account_type` values of `bankcard_ext_info` a card payout accepts.
 *  - `holderNameThreshold` - the amount from which the interface wants the name of the holder, which
 *    wechat only demands for larger payouts.
 *  - `currencies` - the currencies a payout may be booked in. An empty list means "whatever the
 *    merchant account is settled in", which is what stripe and paypal answer.
 *  - `confirmWindow` - the relative time after which the interface closes a payout the recipient never
 *    confirmed and refunds it (wechat).
 *  - `requiresOnboarding` - whether the recipient has to finish an onboarding on the side of the
 *    interface before the first payout (stripe).
 *
 * The object is immutable and built by the interface in `AbstractPaymentProvider::getTransferRules()`.
 * An interface that returns `null` there cannot pay out at all, which is the safe default: refusing a
 * payout beats sending money to an identity nobody validated.
 */
#[Package('framework')]
final readonly class PaymentTransferRules
{
    /**
     * The recipient field every interface knows: the plain handle money is sent to.
     */
    final public const FIELD_IDENTITY_VALUE = 'identityValue';

    /**
     * The name of the person or company the account belongs to.
     */
    final public const FIELD_HOLDER_NAME = 'holderName';

    /**
     * The bank details of a card payout - alipay's `bankcard_ext_info`.
     */
    final public const FIELD_BANK_INFO = 'bankInfo';

    /**
     * @param list<string> $identityTypes the handles the interface can pay out to, e.g. `alipay_open_id`
     * @param list<string> $currencies the iso 4217 codes a payout may be booked in, empty means every currency
     * @param array<string, list<string>> $requiredRecipientFields identity type => recipient fields the interface insists on
     * @param list<string> $bankAccountTypes the `account_type` values of the bank details a card payout accepts
     * @param int|null $holderNameThreshold the amount in the smallest currency unit from which the holder name becomes mandatory
     * @param string|null $confirmWindow the relative modifier (`+24 hours`) after which an unconfirmed payout is closed, null means the interface has no such window
     * @param bool $requiresOnboarding whether a recipient has to be onboarded at the interface before the first payout
     */
    public function __construct(
        public array $identityTypes,
        public array $currencies = [],
        public array $requiredRecipientFields = [],
        public array $bankAccountTypes = [],
        public ?int $holderNameThreshold = null,
        public ?string $confirmWindow = null,
        public bool $requiresOnboarding = false,
    ) {
    }

    /**
     * Whether the interface can pay out to this kind of handle at all.
     *
     * Rejecting the pair at intake beats building a request the interface is guaranteed to refuse, so
     * every payout goes through this question before anything is booked.
     */
    public function supportsIdentityType(string $identityType): bool
    {
        return \in_array($identityType, $this->identityTypes, true);
    }

    /**
     * Whether a payout may be booked in this currency. An empty list means the settlement currency of
     * the merchant account decides, which only the interface can know.
     */
    public function supportsCurrency(string $currency): bool
    {
        return $this->currencies === [] || \in_array(strtoupper($currency), $this->currencies, true);
    }

    /**
     * The recipient fields the interface insists on for this identity type.
     *
     * @return list<string>
     */
    public function requiredFieldsFor(string $identityType): array
    {
        return $this->requiredRecipientFields[$identityType] ?? [];
    }

    /**
     * Whether the interface accepts this `account_type` of a card payout. An empty list means the
     * interface declared no card identity type, so nothing is accepted.
     */
    public function supportsBankAccountType(string $accountType): bool
    {
        return \in_array($accountType, $this->bankAccountTypes, true);
    }

    /**
     * Whether the interface wants the name of the holder for this amount.
     *
     * An interface without a threshold asks for it per identity type instead - see
     * {@see self::requiredFieldsFor()}.
     */
    public function requiresHolderNameFor(int $amount): bool
    {
        return $this->holderNameThreshold !== null && $amount >= $this->holderNameThreshold;
    }
}
