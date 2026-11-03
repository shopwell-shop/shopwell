<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentOrderException extends PaymentException
{
    public static function orderNotFound(string $paymentNo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            'The payment order "{{ paymentNo }}" does not exist.',
            ['paymentNo' => $paymentNo]
        );
    }

    /**
     * The insert of a payment order hit a unique key, but neither the out trade number nor the
     * idempotency key reads the conflicting order back - the order exists, it is just not visible
     * to this request yet, so retrying is the right answer and "not found" would be a lie.
     */
    public static function orderConflict(string $outTradeNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_CONFLICT,
            'The payment order for the out trade number "{{ outTradeNo }}" conflicts with an existing order that cannot be read back.',
            ['outTradeNo' => $outTradeNo]
        );
    }

    public static function orderNotPayable(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_NOT_PAYABLE,
            'The payment order "{{ paymentNo }}" is not open for payment anymore.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function orderAlreadyClosed(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_ALREADY_CLOSED,
            'The payment order "{{ paymentNo }}" is already closed.',
            ['paymentNo' => $paymentNo]
        );
    }

    /**
     * The order cannot be closed because the payment already arrived: the close is refused instead of
     * hiding a payment the platform has to book.
     */
    public static function orderAlreadyPaid(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_ALREADY_PAID,
            'The payment order "{{ paymentNo }}" is already paid and cannot be closed.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function orderNotExpirable(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_NOT_EXPIRABLE,
            'The payment order "{{ paymentNo }}" can no longer expire.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function invalidAmount(int $amount): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_AMOUNT,
            'The amount "{{ amount }}" must be a positive integer in the smallest currency unit.',
            ['amount' => $amount]
        );
    }

    public static function amountMismatch(string $paymentNo, int $expected, int $actual): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::AMOUNT_MISMATCH,
            'The notified amount of "{{ paymentNo }}" is {{ actual }} but {{ expected }} was expected.',
            ['paymentNo' => $paymentNo, 'expected' => $expected, 'actual' => $actual]
        );
    }

    public static function currencyMismatch(string $paymentNo, string $expected, string $actual): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CURRENCY_MISMATCH,
            'The notified currency of "{{ paymentNo }}" is "{{ actual }}" but "{{ expected }}" was expected.',
            ['paymentNo' => $paymentNo, 'expected' => $expected, 'actual' => $actual]
        );
    }

    public static function refundNotAllowed(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::REFUND_NOT_ALLOWED,
            'The payment order "{{ paymentNo }}" is not paid and therefore cannot be refunded.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function refundAmountExceedsOrder(string $paymentNo, int $amount, int $available): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::REFUND_AMOUNT_EXCEEDS_ORDER,
            'The refund amount {{ amount }} of "{{ paymentNo }}" exceeds the refundable amount {{ available }}.',
            ['paymentNo' => $paymentNo, 'amount' => $amount, 'available' => $available]
        );
    }

    public static function refundNotFound(string $refundNo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::REFUND_NOT_FOUND,
            'The refund "{{ refundNo }}" does not exist.',
            ['refundNo' => $refundNo]
        );
    }
}
