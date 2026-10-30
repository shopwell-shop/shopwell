<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Api\Struct;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The payload of the administration when it books a payout.
 *
 * It exists in addition to {@see PaymentTransferRequest} because the two describe different moments of
 * the same payout: this one is the intent of an operator - which recipient, in which app, through which
 * channel - while the request is what the domain service stores. The controller never builds a
 * `PaymentTransferRequest` itself, the booking does: only the booking knows the id of a recipient that
 * was created on the fly, the generated out number and the note that becomes metadata.
 *
 * `amount` is an integer in the smallest unit of the currency (fen for CNY). The administration enters
 * yuan and converts through `yuanToMinorUnits()` - a float must never reach this payload, and the
 * ceiling below is what turns "two zeros too many" into a readable 422 instead of an overflow.
 *
 * The constraints are the first line only: the booking re-validates everything against the declarations
 * of the app and the interface, because this payload can be posted by anyone holding the privilege, not
 * only by the modal of the administration.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentTransferDraft
{
    /**
     * The ceiling of one manual payout in the smallest unit of the currency (100 000 000.00). A manual
     * entry that is off by two zeros has to be refused at intake - the column is a signed integer and
     * an accidental payout of that size is not recoverable.
     */
    final public const MAX_AMOUNT = 10_000_000_000;

    /**
     * The out number is the idempotency key and every interface caps it; see
     * {@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferService}.
     */
    final public const OUT_NO_PATTERN = '/^[0-9a-zA-Z]{1,30}\z/';

    /**
     * @param array<string, mixed>|null $bankInfo alipay's `bankcard_ext_info`, only a card payout needs it
     */
    public function __construct(
        #[Assert\NotBlank]
        public ?string $paymentAppId = null,
        #[Assert\NotBlank]
        public ?string $provider = null,
        #[Assert\NotBlank]
        public ?string $channel = null,
        public ?string $recipientId = null,
        public ?string $identityType = null,
        public ?string $identityValue = null,
        #[Assert\Length(max: 255)]
        public ?string $holderName = null,
        /**
         * @var array<string, mixed>|null
         */
        public ?array $bankInfo = null,
        #[Assert\NotNull]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(self::MAX_AMOUNT)]
        public ?int $amount = null,
        #[Assert\NotBlank]
        // the operator types a code, the platform stores it upper case: normalising before the check
        // keeps a lower case `cny` a usable input instead of a validation error
        #[Assert\Regex(pattern: '/^[A-Z]{3}\z/', normalizer: 'strtoupper')]
        public ?string $currency = null,
        #[Assert\Regex(pattern: self::OUT_NO_PATTERN)]
        public ?string $outNo = null,
        #[Assert\Length(max: 255)]
        public ?string $note = null,
    ) {
    }

    /**
     * Builds the draft from a plain payload.
     *
     * The administration posts the camelCase shape of this object, so the keys are read as they are.
     * The only leniency is the amount: a JSON number may arrive as a numeric string when the body was
     * built by hand, which is accepted exactly like `PaymentTransferRequest::fromArray()` accepts it. A
     * decimal amount is deliberately not coerced - `10.5` is not 10 fen and must never be rounded here.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            paymentAppId: self::string($payload, 'paymentAppId'),
            provider: self::string($payload, 'provider'),
            channel: self::string($payload, 'channel'),
            recipientId: self::string($payload, 'recipientId'),
            identityType: self::string($payload, 'identityType'),
            // the identity value is a credential - it is trimmed but never rewritten
            identityValue: self::identityValue($payload),
            holderName: self::string($payload, 'holderName'),
            bankInfo: self::bankInfo($payload),
            amount: self::amount($payload, 'amount'),
            currency: self::currency($payload),
            outNo: self::string($payload, 'outNo'),
            note: self::string($payload, 'note'),
        );
    }

    /**
     * Whether the payout is booked to a recipient that already exists instead of creating one.
     */
    public function usesExistingRecipient(): bool
    {
        return $this->recipientId !== null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function string(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (!\is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * The identity is a credential: it is trimmed at its edges but nothing else is touched, because the
     * hash and the ciphertext are derived from exactly this value.
     *
     * @param array<string, mixed> $payload
     */
    private static function identityValue(array $payload): ?string
    {
        $value = $payload['identityValue'] ?? null;

        if (!\is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private static function bankInfo(array $payload): ?array
    {
        $value = $payload['bankInfo'] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function amount(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        // a numeric string is the shape a hand built body produces - `ctype_digit` refuses `10.5`,
        // which is what keeps a decimal amount from being truncated into a different payout
        if (\is_string($value) && ctype_digit(trim($value))) {
            return (int) trim($value);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function currency(array $payload): ?string
    {
        $value = self::string($payload, 'currency');

        return $value === null ? null : strtoupper($value);
    }
}
