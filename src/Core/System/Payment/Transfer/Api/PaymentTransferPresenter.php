<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Turns a payout into the answer of the administration - and is the single place that decides what an
 * operator may never see.
 *
 * Three things make this class necessary instead of a few array literals in the controller:
 *
 *  1. **The identity is a credential.** `payment_transfer_recipient.identity_value` is the encrypted
 *     account handle. It is decrypted here, masked, and the plain text is dropped: the browser only ever
 *     receives `zh****23`. That a lookup is not answered with the ciphertext either is the whole reason
 *     {@see self::recipients()} exists - the generic entity endpoint would hand the ciphertext out.
 *  2. **`confirmPayload` is a bearer value.** Whoever knows wechat's `package_info` can claim the money,
 *     so the answer only says whether one exists (`hasConfirmPayload`); the content is never rendered.
 *  3. **`channelParams` is input, not administration data.** It is `api: false` on the entity, and this
 *     presenter keeps it out of the answer on purpose instead of relying on one flag.
 *
 * The transfer rules of the interfaces are read from the registry of the call: `payment_app_account`
 * says which interface an app may use, and the snapshot the factory built for that interface says which
 * identities such a payout accepts, which fields it insists on and which currencies it settles in. Hard
 * coding that matrix in the admin - or reading the raw declarations a second time - would be a second,
 * silently diverging truth.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferPresenter
{
    /**
     * The only keys of `metadata` an operator sees. The column is free form - writing it out as it is
     * would publish whatever a future writer decides to keep there.
     */
    private const VISIBLE_METADATA_KEYS = ['note', 'rejectReason', 'approvedBy', 'approvedAt'];

    public function __construct(
        private readonly PaymentCredentialCipher $cipher,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function transfer(
        PaymentTransferEntity $transfer,
        ?PaymentAppEntity $app = null,
        ?PaymentTransferRecipientEntity $recipient = null,
    ): array {
        $app ??= $transfer->paymentApp;
        $recipient ??= $transfer->recipient;
        $state = $transfer->statusMachineState;

        return [
            'id' => $transfer->id,
            'transferNo' => $transfer->transferNo,
            'outNo' => $transfer->outNo,
            'provider' => $transfer->provider,
            'channel' => $transfer->channel,
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'state' => [
                'id' => $transfer->statusId,
                'technicalName' => $state?->getTechnicalName(),
                'label' => $state?->getName(),
            ],
            'paymentApp' => $app === null ? null : [
                'id' => $app->id,
                'name' => $app->name,
                'technicalName' => $app->technicalName,
            ],
            'recipient' => $recipient === null ? null : $this->recipient($recipient),
            'failureCode' => $transfer->failureCode,
            'failureReason' => $transfer->failureReason,
            'submitAttempts' => $transfer->submitAttempts,
            'providerRef' => $transfer->providerRef,
            'providerStageRef' => $transfer->providerStageRef,
            // deliberately the existence only - the payload itself is a bearer value
            'hasConfirmPayload' => $transfer->confirmPayload !== null && $transfer->confirmPayload !== [],
            'createdAt' => $transfer->getCreatedAt()?->format(\DATE_ATOM),
            'submittedAt' => $transfer->submittedAt?->format(\DATE_ATOM),
            'settledAt' => $transfer->settledAt?->format(\DATE_ATOM),
            'returnedAt' => $transfer->returnedAt?->format(\DATE_ATOM),
            'canceledAt' => $transfer->canceledAt?->format(\DATE_ATOM),
            'expireAt' => $transfer->expireAt?->format(\DATE_ATOM),
            'metadata' => $this->visibleMetadata($transfer->metadata),
        ];
    }

    /**
     * A recipient as the administration may see it: the identity type, the name, the channel account and
     * a masked handle - never the identity value itself, neither plain nor encrypted.
     *
     * @return array<string, mixed>
     */
    public function recipient(PaymentTransferRecipientEntity $recipient): array
    {
        return [
            'id' => $recipient->id,
            'identityType' => $recipient->identityType,
            'holderName' => $recipient->holderName,
            'providerAccountId' => $recipient->providerAccountId,
            'maskedIdentity' => $this->maskIdentity($recipient->identityValue),
            'payoutEnabled' => $recipient->payoutEnabled,
            'onboardingStatus' => $recipient->onboardingStatus,
        ];
    }

    /**
     * @param iterable<PaymentTransferRecipientEntity> $recipients
     *
     * @return list<array<string, mixed>>
     */
    public function recipients(iterable $recipients): array
    {
        $presented = [];

        foreach ($recipients as $recipient) {
            $presented[] = $this->recipient($recipient);
        }

        return $presented;
    }

    /**
     * Everything the "create transfer request" modal renders: the apps, their payout capable interfaces,
     * the channels of those interfaces and the identity rules of each interface.
     *
     * @param list<PaymentAppEntity> $apps
     *
     * @return array<string, mixed>
     */
    public function options(array $apps, PaymentProviderRegistry $registry): array
    {
        $presented = [];
        $defaultAppId = null;

        foreach ($apps as $app) {
            if ($defaultAppId === null) {
                // the caller ordered the platform tenant first - it is the account a shop pays out of
                // until somebody onboards an app of their own
                $defaultAppId = $app->id;
            }

            $presented[] = [
                'id' => $app->id,
                'technicalName' => $app->technicalName,
                'name' => $app->name,
                'type' => $app->type,
                'isPlatform' => $app->type === PaymentAppEntity::TYPE_PLATFORM,
                'accounts' => $this->providersOf($app, $registry),
            ];
        }

        return ['apps' => $presented, 'defaultAppId' => $defaultAppId];
    }

    /**
     * The masked handle of an encrypted identity.
     *
     * `null` is the honest answer for a value that cannot be decrypted: a rotated application secret or a
     * row written before the encryption existed leaves the platform without the plain text, and inventing
     * a placeholder would look like a mask of something.
     */
    public function maskIdentity(?string $encryptedIdentity): ?string
    {
        if ($encryptedIdentity === null || $encryptedIdentity === '') {
            return null;
        }

        try {
            $plain = $this->cipher->decryptValue($encryptedIdentity);
        } catch (\Throwable) {
            return null;
        }

        return self::mask($plain);
    }

    /**
     * A mask keeps what a human needs to recognise an account and drops what would let somebody else use
     * it: an e-mail keeps its first character and its domain, everything else its first and last two
     * characters. Anything short enough to be guessed from a mask is masked completely.
     */
    public static function mask(string $plain): string
    {
        $plain = trim($plain);

        if ($plain === '') {
            return '****';
        }

        $at = strpos($plain, '@');

        if ($at !== false && $at > 0) {
            return mb_substr($plain, 0, 1) . '***' . mb_substr($plain, $at);
        }

        if (mb_strlen($plain) <= 5) {
            return '****';
        }

        return mb_substr($plain, 0, 2) . '****' . mb_substr($plain, -2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function providersOf(PaymentAppEntity $app, PaymentProviderRegistry $registry): array
    {
        $providers = [];

        foreach ($app->accounts ?? [] as $authorization) {
            if (!$authorization instanceof PaymentAppAccountEntity || !$authorization->enabled) {
                continue;
            }

            $name = $authorization->account?->providerTechnicalName;

            if ($name === null || !$authorization->account->active) {
                continue;
            }

            if (!$registry->has($name)) {
                continue;
            }

            $provider = $registry->get($name);
            $rules = $provider->transferRules;

            // an interface that cannot pay out, or that declares no payout rules, must not appear in a
            // modal that books a payout - the operator would fill a form that can only be refused
            if ($rules === null || !$provider->supports(PaymentCapability::TRANSFER)) {
                continue;
            }

            $channels = $this->channelsOf($app, $provider, $rules);

            if ($channels === []) {
                continue;
            }

            $providers[] = [
                'technicalName' => $provider->technicalName,
                'accountId' => $authorization->accountId,
                'name' => $authorization->account->name,
                // the label of a provider is a snippet key, the administration translates it
                'label' => $provider->label,
                'identityTypes' => $rules->identityTypes,
                'requiredRecipientFields' => $rules->requiredRecipientFields,
                'bankAccountTypes' => $rules->bankAccountTypes,
                'holderNameThreshold' => $rules->holderNameThreshold,
                'requiresOnboarding' => $rules->requiresOnboarding,
                'currencies' => $rules->currencies,
                'channels' => $channels,
            ];
        }

        return $providers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function channelsOf(PaymentAppEntity $app, PaymentProvider $provider, PaymentTransferRules $rules): array
    {
        $channels = [];

        foreach ($app->channels ?? [] as $row) {
            if (!$row instanceof PaymentAppChannelEntity || !$row->enabled) {
                continue;
            }

            $name = $row->channelTechnicalName;
            $channel = $provider->products[$name] ?? null;

            // the declaration is the authority on which interface a channel belongs to - a name prefix
            // would be a guess, and a channel the interface does not declare can never be opened
            if (!$channel instanceof PaymentProduct || $channel->providerTechnicalName !== $provider->technicalName) {
                continue;
            }

            $channels[] = [
                'technicalName' => $channel->technicalName,
                'label' => $channel->label,
                // the rules of the interface win: they are what the payout is validated against, and the
                // channel currencies are only the finer limit when the interface declares none
                'currencies' => $rules->currencies === [] ? $channel->currencies : $rules->currencies,
            ];
        }

        return $channels;
    }

    /**
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>
     */
    private function visibleMetadata(?array $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        return array_intersect_key($metadata, array_flip(self::VISIBLE_METADATA_KEYS));
    }
}
