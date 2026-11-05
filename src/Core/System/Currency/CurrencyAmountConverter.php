<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Intl\Currencies;

/**
 * Converts decimal amounts to and from integer minor units, without exchanging currencies.
 * Cash rounding belongs to the cart; provider-specific unit rules belong to the integration.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
final class CurrencyAmountConverter
{
    private function __construct()
    {
    }

    public static function fractionDigits(string $currency): int
    {
        $currency = strtoupper($currency);
        if (!Currencies::exists($currency)) {
            throw CurrencyException::unsupportedCurrency($currency);
        }

        return Currencies::getFractionDigits($currency);
    }

    public static function toDecimal(int $amount, string $currency): string
    {
        $scale = self::fractionDigits($currency);
        if ($scale === 0) {
            return (string) $amount;
        }

        // abs(PHP_INT_MIN) becomes a float; retain the digits as a string instead.
        $digits = str_pad(ltrim((string) $amount, '-'), $scale + 1, '0', \STR_PAD_LEFT);

        return ($amount < 0 ? '-' : '') . substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
    }

    /**
     * Accepts the checkout's float representation only at its boundary.
     */
    public static function fromFloat(float $amount, string $currency): int
    {
        return self::toMinor(number_format($amount, self::fractionDigits($currency), '.', ''), $currency);
    }

    public static function toFloat(int $amount, string $currency): float
    {
        return (float) self::toDecimal($amount, $currency);
    }

    /**
     * Rounds half up to the currency's minor unit; out-of-range amounts must never be saturated.
     */
    public static function toMinor(string $amount, string $currency): int
    {
        $scale = self::fractionDigits($currency);
        if (preg_match('/^(?<sign>-?)(?<integer>\d+)(?:\.(?<fraction>\d*))?\z/', trim($amount), $matches) !== 1) {
            throw CurrencyException::invalidAmount($amount, $currency);
        }

        $fraction = str_pad($matches['fraction'] ?? '', $scale + 1, '0');
        $digits = ltrim($matches['integer'] . substr($fraction, 0, $scale), '0');
        $minor = filter_var($matches['sign'] . ($digits === '' ? '0' : $digits), \FILTER_VALIDATE_INT);
        $roundUp = $fraction[$scale] >= '5';

        if ($minor === false || ($roundUp && ($minor === \PHP_INT_MAX || $minor === \PHP_INT_MIN))) {
            throw CurrencyException::invalidAmount($amount, $currency);
        }

        return $minor + ($roundUp ? ($matches['sign'] === '-' ? -1 : 1) : 0);
    }
}
