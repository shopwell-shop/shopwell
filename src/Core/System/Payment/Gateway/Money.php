<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Converts between the amounts of the platform and the amounts of the channels.
 *
 * The platform always works with integers in the smallest unit of the currency (fen for CNY), while
 * some channels expect a decimal string ("0.01") and others the minor unit as an integer. All of it is
 * done with string arithmetic, never with floats, so no amount is ever rounded by accident.
 *
 * @internal
 */
#[Package('framework')]
final class Money
{
    /**
     * The currencies that do not use two fractional digits.
     */
    private const EXPONENTS = [
        'BHD' => 3,
        'CLF' => 4,
        'JPY' => 0,
        'KRW' => 0,
        'KWD' => 3,
        'OMR' => 3,
        'TND' => 3,
        'VND' => 0,
    ];

    private function __construct()
    {
    }

    public static function exponent(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }

    /**
     * Turns the minor unit of the platform into the decimal string a channel expects.
     */
    public static function toDecimal(int $amount, string $currency): string
    {
        $exponent = self::exponent($currency);

        if ($exponent === 0) {
            return (string) $amount;
        }

        $negative = $amount < 0;
        $digits = str_pad((string) abs($amount), $exponent + 1, '0', \STR_PAD_LEFT);
        $decimal = substr($digits, 0, -$exponent) . '.' . substr($digits, -$exponent);

        return $negative ? '-' . $decimal : $decimal;
    }

    /**
     * Turns the decimal string a channel sent into the minor unit of the platform.
     *
     * @throws PaymentException
     */
    public static function toMinor(string $amount, string $currency): int
    {
        $exponent = self::exponent($currency);
        $amount = trim($amount);

        if (preg_match('/^(?<sign>-?)(?<integer>\d+)(?:\.(?<fraction>\d*))?$/', $amount, $matches) !== 1) {
            throw PaymentException::invalidAmount(0);
        }

        // one digit more than the currency has, so that the amount can be rounded half up
        $fraction = str_pad($matches['fraction'] ?? '', $exponent + 1, '0');
        $minor = (int) ($matches['integer'] . substr($fraction, 0, $exponent));

        if ((int) $fraction[$exponent] >= 5) {
            ++$minor;
        }

        return $matches['sign'] === '-' ? -$minor : $minor;
    }
}
