<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Submits a payout to paypal, `POST /v1/payments/payouts`.
 *
 * The interface only knows batches: a payout is a batch of items and a single payout is a batch of
 * one. The batch id is derived from the out number of the transfer, so it is stable across retries
 * and a repeated call is answered with the batch of the first one instead of moving the money twice.
 *
 * **A batch answered as `SUCCESS` is not a payout that arrived.** The status of a batch speaks about
 * the batch - an item whose recipient has no paypal account yet sits in it as `UNCLAIMED` and the
 * batch is `SUCCESS` anyway; the money is unclaimed for 30 days and then returns. Only the
 * `transaction_status` of the item is evidence the money arrived, and the create answer does not
 * carry it reliably, so the submission never answers `succeeded`: it answers `processing` and the
 * query converges the transfer from the item.
 *
 * The identity of the recipient is stored encrypted; the action decrypts it with the credential
 * cipher right before the request is built, so the plain text never lives longer than one call.
 *
 * The class deliberately carries **no list of the currencies paypal pays out in**. Whether paypal
 * accepts CNY as a payout currency is unconfirmed (the design doc keeps it open in §8.2), and a local
 * gate would block legitimate money movement on a guess - a refusal of the interface is kept in
 * `failure_code` and surfaced by the state machine instead.
 *
 * The request carries the model the driver prepared with `PaymentGatewayModel::forTransfer()` - the
 * record of the attempt and the {@see TransferResult} live on it, the driver reads them the way it
 * reads the model of a payment order.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalTransferAction extends AbstractPaymentAction
{
    /**
     * The identity types of the recipients of paypal, mapped to the `recipient_type` of the wire
     * format. A paypal payer id is a `PAYPAL_ID`, the email of an account an `EMAIL`.
     */
    final public const IDENTITY_TYPES = [
        'paypal_email' => 'EMAIL',
        'paypal_phone' => 'PHONE',
        'paypal_payer_id' => 'PAYPAL_ID',
    ];

    final public const BATCH_STATUS_DENIED = 'DENIED';
    final public const BATCH_STATUS_PENDING = 'PENDING';
    final public const BATCH_STATUS_PROCESSING = 'PROCESSING';
    final public const BATCH_STATUS_SUCCESS = 'SUCCESS';
    final public const BATCH_STATUS_CANCELED = 'CANCELED';

    final public const ITEM_STATUS_SUCCESS = 'SUCCESS';
    final public const ITEM_STATUS_FAILED = 'FAILED';
    final public const ITEM_STATUS_PENDING = 'PENDING';
    final public const ITEM_STATUS_UNCLAIMED = 'UNCLAIMED';
    final public const ITEM_STATUS_ONHOLD = 'ONHOLD';
    final public const ITEM_STATUS_BLOCKED = 'BLOCKED';
    final public const ITEM_STATUS_RETURNED = 'RETURNED';
    final public const ITEM_STATUS_REFUNDED = 'REFUNDED';
    final public const ITEM_STATUS_REVERSED = 'REVERSED';

    /**
     * Paypal caps `sender_batch_id` and `sender_item_id` at 30 characters, and the platform caps the
     * out number at the same length for exactly this reason - see
     * {@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferService}. The shortening below is
     * therefore a defence against a row that predates the cap, not the normal path.
     */
    private const MAX_BATCH_ID_LENGTH = 30;

    private const BATCH_ID_PREFIX_LENGTH = 22;

    private const BATCH_ID_HASH_LENGTH = 8;

    /**
     * The limit paypal documents for `email_subject` and `note` of a payout item. Both fields are
     * documented as 255 *single-byte* characters; that wording could not be re-verified against the
     * live reference, so the cut is made on bytes instead of characters - a multi-byte subject would
     * otherwise pass a character limit and still exceed what paypal accepts.
     */
    private const MAX_EMAIL_SUBJECT_BYTES = 255;

    private const MAX_NOTE_BYTES = 255;

    protected string $apiClass = PaypalApi::class;

    public function __construct(private readonly PaymentCredentialCipher $cipher)
    {
    }

    public function capability(): PaymentCapability
    {
        return PaymentCapability::TRANSFER;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        if (!$request instanceof PaymentTransferRequest) {
            throw RequestNotSupportedException::create($request);
        }

        $api = $this->api();
        $model = $this->modelOf($request);

        $transfer = $request->transfer;

        // the subject comes from the model - the driver prepared it with forTransfer(), the channel
        // does not derive it a second time from the entity
        $subject = $this->stringValue($model, PaymentGatewayModel::SUBJECT);

        $content = self::contentOf($transfer, $request->recipient, $this->cipher, $subject, $api);

        $this->recordRequest($model, $content);

        // the api answers a rejection instead of throwing, so a failure of the interface is mapped by
        // resultOf() like the answer of a call that went through
        $answer = $api->createPayout($content, self::batchIdOf($transfer->outNo));

        $this->recordResponse($model, $answer);

        $this->setResult($model, self::resultOf($answer));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentTransferRequest::class);
    }

    /**
     * The batch id paypal is called with. It names the business case, so it is derived from the out
     * number of the transfer and nothing else.
     *
     * The derivation is deliberately not `substr($outNo, 0, 30)`: a lossy cut would let two out
     * numbers that share their first 30 characters collide on the same batch id - paypal would answer
     * "duplicate batch", the platform would read a retry where in fact the money of another transfer
     * had already been moved. The first 8 characters of a sha256 keep the two apart, so a 30 character
     * id is the out number itself and a longer one is its first 22 characters plus 8 of the hash.
     *
     * The platform caps the out number at 30 since paypal is one of the channels a payout can be sent
     * through, so this is an identity in practice and only the branch for a row that predates the cap
     * ever shortens. It is kept because the derivation must stay the same for payouts that are
     * already in flight - changing it would make paypal see a new batch for an out number it paid.
     *
     * The out number is `[0-9a-zA-Z]` only, so its character and byte lengths are the same and the
     * result never exceeds 30 characters.
     *
     * Deterministic on purpose: the query and the (later) webhook recompute the very same id from the
     * out number. Changing the derivation breaks the idempotency of every transfer that is in flight,
     * because paypal would see a new batch for an out number it already paid out.
     */
    public static function batchIdOf(string $outNo): string
    {
        if (\strlen($outNo) <= self::MAX_BATCH_ID_LENGTH) {
            return $outNo;
        }

        return substr($outNo, 0, self::BATCH_ID_PREFIX_LENGTH)
            . substr(Hasher::hash($outNo, 'sha256'), 0, self::BATCH_ID_HASH_LENGTH);
    }

    /**
     * The business content of the payout, in the snake_case of the wire format.
     *
     * The subject comes from the model, not from the entities - the model is the one source of the
     * payout the record and the wire content are built from, so they can not tell different stories.
     * Only the cut to the limit of the interface happens here, because that limit is paypal's, not a
     * rule of the platform.
     *
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    public static function contentOf(
        PaymentTransferEntity $transfer,
        PaymentTransferRecipientEntity $recipient,
        PaymentCredentialCipher $cipher,
        string $subject,
        PaypalApi $api,
    ): array {
        $recipientType = self::IDENTITY_TYPES[$recipient->identityType] ?? null;

        if ($recipientType === null) {
            throw PaymentException::transferIdentityNotSupported($transfer->provider, $recipient->identityType);
        }

        // the identity is a credential at rest and is decrypted for this call alone
        $receiver = $cipher->decryptValue($recipient->identityValue);

        if (trim($receiver) === '') {
            throw PaymentException::transferRecipientIncomplete('identity_value');
        }

        $subject = $subject !== '' ? $subject : PaymentGatewayModel::DEFAULT_SUBJECT;
        $batchId = self::batchIdOf($transfer->outNo);

        return [
            'sender_batch_header' => [
                'sender_batch_id' => $batchId,
                'email_subject' => mb_strcut($subject, 0, self::MAX_EMAIL_SUBJECT_BYTES),
            ],
            'items' => [[
                'recipient_type' => $recipientType,
                'receiver' => $receiver,
                'amount' => [
                    'currency' => $transfer->currency,
                    'value' => $api->amount($transfer->amount, $transfer->currency),
                ],
                'sender_item_id' => $batchId,
                'note' => mb_strcut($subject, 0, self::MAX_NOTE_BYTES),
            ]],
        ];
    }

    /**
     * The answer of the interface, narrowed to what a transfer means.
     *
     * Pure on purpose: the same answer arrives at every retry and what it means is decided here and
     * nowhere else.
     *
     * @param array<string, mixed> $answer
     */
    public static function resultOf(array $answer): TransferResult
    {
        if (isset($answer['error'])) {
            return new TransferResult(
                TransferResult::STATE_FAILED,
                failureCode: self::errorCodeOf($answer) ?? 'payout',
            );
        }

        $batch = \is_array($answer['batch_header'] ?? null) ? $answer['batch_header'] : [];
        $status = self::stringOf($batch, 'batch_status');
        $state = self::stateOfBatch($status);

        return new TransferResult(
            $state,
            providerRef: self::stringOf($batch, 'payout_batch_id') ?: null,
            failureCode: $state === TransferResult::STATE_FAILED ? $status : null,
        );
    }

    /**
     * The status of a batch, mapped to what the platform does with the transfer.
     *
     * `SUCCESS` maps to `processing` and not to `succeeded`: the batch is `SUCCESS` even when its
     * single item is `UNCLAIMED` - the recipient has no paypal account yet, the money sits unclaimed
     * for 30 days and then returns. The batch status is therefore no evidence that the money arrived;
     * only the item status is, and the query (and the webhook of a later stage) carries it.
     */
    public static function stateOfBatch(string $status): string
    {
        return match ($status) {
            self::BATCH_STATUS_DENIED, self::BATCH_STATUS_CANCELED => TransferResult::STATE_FAILED,
            self::BATCH_STATUS_PENDING, self::BATCH_STATUS_PROCESSING, self::BATCH_STATUS_SUCCESS => TransferResult::STATE_PROCESSING,
            default => TransferResult::STATE_UNKNOWN,
        };
    }

    /**
     * The status of one item of a batch, mapped to what the platform does with the transfer. This is
     * the truth about the money - the batch status is only a fallback for an answer that carries no
     * item.
     */
    public static function stateOfItem(string $status): string
    {
        return match ($status) {
            self::ITEM_STATUS_SUCCESS => TransferResult::STATE_SUCCEEDED,
            self::ITEM_STATUS_FAILED, self::ITEM_STATUS_BLOCKED => TransferResult::STATE_FAILED,
            self::ITEM_STATUS_PENDING, self::ITEM_STATUS_UNCLAIMED, self::ITEM_STATUS_ONHOLD => TransferResult::STATE_PROCESSING,
            self::ITEM_STATUS_RETURNED, self::ITEM_STATUS_REFUNDED, self::ITEM_STATUS_REVERSED => TransferResult::STATE_RETURNED,
            default => TransferResult::STATE_UNKNOWN,
        };
    }

    /**
     * The failure code of an item that will never arrive, from the errors of the item when it names
     * one and from the status itself otherwise.
     *
     * @param array<string, mixed> $item
     */
    public static function failureCodeOfItem(array $item, string $status): string
    {
        $errors = \is_array($item['errors'] ?? null) ? $item['errors'] : [];
        $first = \is_array($errors[0] ?? null) ? $errors[0] : [];

        return self::stringOf($first, 'name') ?: $status;
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

        return $api;
    }

    /**
     * The code of a rejection of the interface, or null when the answer carries none.
     *
     * @param array<string, mixed> $answer
     */
    private static function errorCodeOf(array $answer): ?string
    {
        $error = \is_array($answer['error'] ?? null) ? $answer['error'] : [];

        return \is_string($error['code'] ?? null) && $error['code'] !== '' ? $error['code'] : null;
    }

    /**
     * @param array<string, mixed> $answer
     */
    private static function stringOf(array $answer, string $key): string
    {
        $value = $answer[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }
}
