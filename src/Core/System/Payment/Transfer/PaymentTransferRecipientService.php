<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;

/**
 * Keeps the recipients of payouts, one row per identity per app and interface.
 *
 * The interfaces disagree on what identifies a recipient, and each interface writes that disagreement
 * down in its own {@see PaymentTransferRules}: an alipay uid is the same person under every app, a
 * wechat openid only exists inside the very app that issued it, and paypal accepts an email long before
 * the person has an account. Accepting an identity the interface cannot pay out to would produce a
 * request that is guaranteed to be refused - so the rules are validated here, at intake, and not in the
 * channel adapters. Nothing in this class names a channel: a further interface joins by declaring its
 * rules, not by being added to a matrix here.
 *
 * The identity value is a credential (a card number, an openid, an account id) and is encrypted with
 * the same cipher as the app secret. The cipher is not deterministic, so a unique key can not span the
 * ciphertext; it spans `identity_hash` - sha256 over the plain text - instead. See
 * {@see \Shopwell\Core\Migration\V6_7\Migration1792700020AddTransferRecipientIdentityHash}.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferRecipientService
{
    /**
     * The identity is stored encrypted, and the ciphertext has to fit into the `identity_value`
     * column (`VARCHAR(255)`): the cipher returns `base64_encode(nonce(24) + secretbox(plain + 16))`,
     * which is `4 * ceil((n + 40) / 3)` characters, so the plain text may be at most 149 bytes. The
     * guard measures bytes, not characters - the cipher and the column both do. Checking it here
     * turns a "Data too long" 500 into a readable 422; see
     * {@see PaymentException::transferIdentityValueTooLong()}.
     */
    private const MAX_IDENTITY_LENGTH = 149;

    /**
     * @param EntityRepository<PaymentTransferRecipientCollection> $recipientRepository
     */
    public function __construct(
        private readonly EntityRepository $recipientRepository,
        private readonly PaymentCredentialCipher $cipher,
        private readonly ClockInterface $clock,
        private readonly PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * Returns the recipient of this identity, creating it on first use.
     *
     * The lookup is by the identity hash, not by the identity value: the value is encrypted with a
     * random nonce and a second call with the same plain text would produce different ciphertext.
     *
     * The payout readiness differs by interface and is written explicitly, not left to a default: an
     * interface whose rules demand an onboarding (stripe) has to wait for the `account.updated`
     * webhook to mirror `payouts_enabled`, while an interface without that concept pays out to a
     * recipient it accepts immediately. Unifying both branches into `true` would make the platform
     * promise payouts the interface refuses, unifying them into `false` would block every transfer.
     *
     * @param array<string, mixed>|null $bankInfo alipay's `bankcard_ext_info`, only a card needs it
     *
     * @throws PaymentException
     */
    public function createOrGet(
        PaymentAppEntity $app,
        string $provider,
        string $identityType,
        string $identityValue,
        ?string $holderName,
        ?array $bankInfo,
    ): PaymentTransferRecipientEntity {
        $rules = $this->rulesOf($provider, $identityType);
        $this->assertIdentityComplete($rules, $identityType, $identityValue, $holderName, $bankInfo);

        $hash = $this->hashIdentity($app->id, $provider, $identityType, $identityValue);

        $existing = $this->findByHash($app->id, $provider, $identityType, $hash);
        if ($existing !== null) {
            return $existing;
        }

        $requiresOnboarding = $rules->requiresOnboarding;
        $id = Uuid::randomHex();

        $row = [
            'id' => $id,
            'paymentAppId' => $app->id,
            'provider' => $provider,
            'identityType' => $identityType,
            // the plain text never reaches the database: the column is a credential
            'identityValue' => $this->cipher->encryptValue($identityValue),
            'identityHash' => $hash,
            'holderName' => $holderName,
            'bankInfo' => $bankInfo,
            // written explicitly - the dal only consults EntityDefinition::getDefaults() and never
            // writes a property initial value, and the column has no ddl default
            'onboardingStatus' => $requiresOnboarding
                ? PaymentTransferStates::ONBOARDING_PENDING
                : PaymentTransferStates::ONBOARDING_REGISTERED,
            'payoutEnabled' => !$requiresOnboarding,
        ];

        try {
            $this->recipientRepository->create([$row], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            // the unique key over (app, provider, identity type, hash) was hit: the very same identity
            // was created between our lookup and our insert, and that row is the answer
            $race = $this->findByHash($app->id, $provider, $identityType, $hash);

            if ($race === null) {
                throw PaymentException::transferConflict($hash);
            }

            return $race;
        }

        return $this->getById($id);
    }

    /**
     * @throws PaymentException
     */
    public function getById(string $id): PaymentTransferRecipientEntity
    {
        $recipient = $this->recipientRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        if (!$recipient instanceof PaymentTransferRecipientEntity) {
            throw PaymentException::transferRecipientNotFound($id);
        }

        return $recipient;
    }

    /**
     * Mirrors what stripe says about the account a payout goes to. It is called by the
     * `account.updated` webhook - nothing else may change these fields, they belong to the channel.
     *
     * `restricted` is not expressible here on purpose: `payouts_enabled` alone can not tell an account
     * that is still onboarding from one stripe has restricted. The discriminating signal is
     * `requirements.disabled_reason` on the account object - non-empty means restricted - which this
     * call does not receive yet; it becomes available when T3 wires the `account.updated` webhook, and
     * the mapping belongs here then. Until it arrives, `pending` is the honest mirror of "not payable,
     * not yet onboarded".
     *
     * @throws PaymentException
     */
    public function markStripeAccountReady(string $recipientId, string $accountId, bool $payoutEnabled): void
    {
        $this->getById($recipientId);

        $this->recipientRepository->update([[
            'id' => $recipientId,
            'providerAccountId' => $accountId,
            'payoutEnabled' => $payoutEnabled,
            'onboardingStatus' => $payoutEnabled
                ? PaymentTransferStates::ONBOARDING_REGISTERED
                : PaymentTransferStates::ONBOARDING_PENDING,
            // the moment the account became usable is the moment it was verified - and it stops being
            // verified when the interface disables payouts again
            'verifiedAt' => $payoutEnabled ? $this->clock->now() : null,
        ]], Context::createDefaultContext());
    }

    /**
     * A transfer may only be booked to a recipient that can actually receive the money at this
     * amount. An interface may make a field mandatory from an amount on - wechat demands the name of
     * the holder from 2000 yuan on - and the threshold is read from its rules, not hardcoded here.
     *
     * @throws PaymentException
     */
    public function assertPayoutReady(PaymentTransferRecipientEntity $recipient, int $amount): void
    {
        $rules = $this->providers->has($recipient->provider)
            ? $this->providers->get($recipient->provider)->transferRules
            : null;

        if ($rules !== null && $rules->requiresHolderNameFor($amount) && $recipient->holderName === null) {
            throw PaymentException::transferRecipientIncomplete('holderName');
        }
    }

    /**
     * The payout rules of the interface, or a refusal when it declared none: an interface without
     * rules can not pay out to anything, and the safe answer is to reject the identity here instead of
     * building a request the interface would refuse anyway.
     *
     * @throws PaymentException
     */
    private function rulesOf(string $provider, string $identityType): PaymentTransferRules
    {
        $rules = $this->providers->has($provider)
            ? $this->providers->get($provider)->transferRules
            : null;

        if ($rules === null || !$rules->supportsIdentityType($identityType)) {
            throw PaymentException::transferIdentityNotSupported($provider, $identityType);
        }

        return $rules;
    }

    /**
     * The fields the interface demands for this very combination, taken from its rules - alipay needs
     * the name of the holder for a logon id, and the name plus the bank details for a card.
     *
     * @param array<string, mixed>|null $bankInfo
     *
     * @throws PaymentException
     */
    private function assertIdentityComplete(
        PaymentTransferRules $rules,
        string $identityType,
        string $identityValue,
        ?string $holderName,
        ?array $bankInfo,
    ): void {
        if (trim($identityValue) === '') {
            throw PaymentException::transferRecipientIncomplete('identityValue');
        }

        // before the hash is computed and the plain text is encrypted: the ciphertext is what has to
        // fit into the column, and a request that cannot be stored must fail with 422, not 500. The
        // limit is measured in bytes because that is what the cipher and the column measure - a
        // multibyte identity runs out of room long before it runs out of characters.
        $length = \strlen($identityValue);
        if ($length > self::MAX_IDENTITY_LENGTH) {
            throw PaymentException::transferIdentityValueTooLong($length);
        }

        foreach ($rules->requiredFieldsFor($identityType) as $field) {
            if ($field === PaymentTransferRules::FIELD_HOLDER_NAME && $holderName === null) {
                throw PaymentException::transferRecipientIncomplete('holderName');
            }

            if ($field === PaymentTransferRules::FIELD_BANK_INFO && $bankInfo === null) {
                throw PaymentException::transferRecipientIncomplete('bankInfo');
            }
        }

        if ($bankInfo === null || $rules->bankAccountTypes === []) {
            return;
        }

        $accountType = $bankInfo['account_type'] ?? null;

        if (!\is_scalar($accountType) || !$rules->supportsBankAccountType((string) $accountType)) {
            throw PaymentException::transferRecipientIncomplete('bankInfo.account_type');
        }
    }

    /**
     * The deterministic half of the identity, and the only thing a unique key may be built on: the
     * ciphertext of the value changes on every write.
     *
     * The app id is part of the input, so the same identity under two apps is two recipients - which
     * is what the interfaces expect (a wechat openid belongs to one app, an alipay uid is scoped to
     * the merchant account).
     */
    private function hashIdentity(string $appId, string $provider, string $identityType, string $identityValue): string
    {
        return Hasher::hash(implode('|', [$appId, $provider, $identityType, $identityValue]), 'sha256');
    }

    /**
     * @throws PaymentException
     */
    private function findByHash(string $appId, string $provider, string $identityType, string $hash): ?PaymentTransferRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('provider', $provider),
            new EqualsFilter('identityType', $identityType),
            new EqualsFilter('identityHash', $hash)
        );
        $criteria->setLimit(1);

        $recipient = $this->recipientRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $recipient instanceof PaymentTransferRecipientEntity ? $recipient : null;
    }
}
