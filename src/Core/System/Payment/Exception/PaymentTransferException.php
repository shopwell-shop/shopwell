<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferException extends PaymentException
{
    public static function transferNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOT_FOUND,
            'The payment transfer "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    /**
     * The insert hit a unique key, but the conflicting row cannot be read back in this request - it
     * exists, it is just not visible yet, so retrying is the right answer and "not found" would be a
     * lie. Both unique keys of the domain report through this factory: the out number of a transfer
     * and the identity hash of a recipient.
     */
    public static function transferConflict(string $reference): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_CONFLICT,
            'The payment transfer "{{ reference }}" conflicts with an existing row that cannot be read back.',
            ['reference' => $reference]
        );
    }

    public static function transferRecipientNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_RECIPIENT_NOT_FOUND,
            'The payment transfer recipient "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    /**
     * The interface cannot pay out to this kind of identity at all - wechat only knows an openid of
     * the very app, stripe only a connected account, alipay no paypal email. Rejecting the pair at
     * intake beats building a request the interface is guaranteed to refuse.
     */
    public static function transferIdentityNotSupported(string $provider, string $identityType): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_IDENTITY_NOT_SUPPORTED,
            'The payment interface "{{ provider }}" cannot pay out to the identity type "{{ identityType }}".',
            ['provider' => $provider, 'identityType' => $identityType]
        );
    }

    /**
     * A field the interface demands for this combination is missing - the name of the holder for an
     * alipay logon id, the bank details for a card, the name of the holder from 2000 yuan on at wechat.
     */
    public static function transferRecipientIncomplete(string $field): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_RECIPIENT_INCOMPLETE,
            'The transfer recipient is missing the field "{{ field }}" that the interface requires.',
            ['field' => $field]
        );
    }

    /**
     * The plain identity has to fit into the encrypted column, and the ciphertext is larger than the
     * plain text: `PaymentCredentialCipher::encryptValue()` returns
     * `base64_encode(nonce(24) + sodium secretbox(plain + 16))`, so a plain text of `n` bytes becomes
     * `4 * ceil((n + 40) / 3)` characters. With `identity_value` being `VARCHAR(255)` the limit is
     * therefore `4 * ceil((n + 40) / 3) <= 255`, i.e. `n <= 149`.
     *
     * The limit is enforced before encrypting on purpose: the write would otherwise fail with MySQL
     * error 1406 ("Data too long") and surface as a 500 instead of a readable 422. It is not a reason
     * to widen the column - 149 bytes already cover every real identity (paypal's `receiver` tops out
     * at 127 characters, and every identity type is ASCII), and a wider column only wastes storage on
     * ciphertext.
     */
    public static function transferIdentityValueTooLong(int $length): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_IDENTITY_VALUE_TOO_LONG,
            'The transfer recipient identity is {{ length }} bytes long and must not exceed 149 bytes, so that its encrypted form fits into the 255 character column.',
            ['length' => $length]
        );
    }

    public static function transferAmountInvalid(): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_AMOUNT_INVALID,
            'The transfer amount must be a positive integer in the smallest currency unit.',
            []
        );
    }

    /**
     * The out number is the idempotency key of a payout and the interfaces cap it: wechat allows 32
     * characters of `[0-9a-zA-Z]` on `out_bill_no`, alipay 64 on `out_biz_no`, and paypal 30 on the
     * batch and item ids it derives from the number. The strictest limit wins, and a payout that
     * cannot be sent under the number it was booked with must never be booked at all.
     */
    public static function transferOutNoInvalid(string $outNo): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_OUT_NO_INVALID,
            'The out number "{{ outNo }}" must consist of 1 to 30 characters of [0-9a-zA-Z].',
            ['outNo' => $outNo]
        );
    }

    /**
     * The out number already names a **different** payout of this app.
     *
     * The out number is the idempotency key, so a second booking under it is answered with the payout that
     * exists - but only when it is the same payout. Reusing the number for another recipient, amount or
     * currency would make the platform answer "already booked" with a payout the caller never meant, and the
     * intended money would never move. The conflict is the only answer that lets the caller fix the number.
     */
    public static function transferOutNoTaken(string $outNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_OUT_NO_TAKEN,
            'The out number "{{ outNo }}" is already used by another payout of this app.',
            ['outNo' => $outNo]
        );
    }

    /**
     * The app a payout is booked for does not exist or is switched off.
     *
     * It carries `PAYMENT__APP_NOT_FOUND` on purpose - the code an operator sees should stay the one the
     * domain already uses for "this app cannot be worked with" - but answers 404 instead of the 401 of
     * {@see PaymentAppException::appNotFound()}: that one is the answer of the app authentication of the payment api, and a
     * 401 in the administration would read as "your session expired", which is not what happened.
     */
    public static function transferAppNotAvailable(string $appId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::APP_NOT_FOUND,
            'The payment app "{{ appId }}" does not exist or is not active.',
            ['appId' => $appId]
        );
    }

    /**
     * The transfer is already past the point of submission. Sending it again would not be a retry but
     * a second payout - a retry always queries the channel first ("query before sending").
     */
    public static function transferAlreadyFinalized(string $outNo, string $state): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_ALREADY_FINALIZED,
            'The payment transfer "{{ outNo }}" is "{{ state }}" and can not be submitted again.',
            ['outNo' => $outNo, 'state' => $state]
        );
    }

    /**
     * The credentials of a payout are resolved from the app the caller acts for, so a transfer of
     * another app would be paid out of that merchant account. The out number is the business case and
     * is what the caller can act on - the transfer id is an internal detail.
     */
    public static function transferForeignApp(string $appId, string $outNo): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::TRANSFER_FOREIGN_APP,
            'The payment transfer "{{ outNo }}" does not belong to the app "{{ appId }}".',
            ['appId' => $appId, 'outNo' => $outNo]
        );
    }

    /**
     * A transfer that reached the interface has to name the object the interface created - every later
     * query and every notification is matched by it. A row in an open state without one is a data
     * defect of the submission, not a missing field of the recipient, and has to be reported as such:
     * no query can be made and no answer can be booked.
     */
    public static function transferProviderRefMissing(string $outNo): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TRANSFER_PROVIDER_REF_MISSING,
            'The payment transfer "{{ outNo }}" carries no provider reference and can not be queried at the channel.',
            ['outNo' => $outNo]
        );
    }

    /**
     * The interface does not pay out to a recipient that has not finished its onboarding: stripe
     * refuses every payout of a connected account whose `payouts_enabled` is not true. Refusing the
     * call locally - before a single request is built - beats using the error code of the interface
     * as a validator, which would burn the idempotency key of the transfer on a guaranteed refusal.
     */
    public static function transferRecipientNotReady(string $recipientId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_RECIPIENT_NOT_READY,
            'The transfer recipient "{{ recipientId }}" is not ready to be paid out on the side of the interface.',
            ['recipientId' => $recipientId]
        );
    }

    /**
     * A payout notification names an app in its url, and an app that does not exist has no merchant
     * account whose signing key could verify the notification. The endpoint answers it the same way it
     * answers an unknown interface: not found, without saying which of the two was wrong.
     */
    public static function transferNotificationAppUnknown(string $appId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOTIFICATION_APP_UNKNOWN,
            'The app "{{ appId }}" of the payout notification does not exist.',
            ['appId' => $appId]
        );
    }

    /**
     * A payout notification can only be verified by the interface that sent it. An interface the
     * platform does not ship has no verifier, and guessing one would mean trusting an unverified body.
     */
    public static function transferNotificationProviderUnknown(string $provider): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOTIFICATION_PROVIDER_UNKNOWN,
            'The payment interface "{{ provider }}" can not verify a payout notification.',
            ['provider' => $provider]
        );
    }

    /**
     * The signed body of a payout notification named a payout the app does not have. It is a defect of
     * the caller or a payout of another merchant account, and neither becomes true by repeating the
     * notification.
     */
    public static function transferNotificationTransferUnknown(string $outNo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOTIFICATION_TRANSFER_UNKNOWN,
            'The payout "{{ outNo }}" named by the notification does not exist for this app.',
            ['outNo' => $outNo]
        );
    }
}
