<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * Masks all but the head of the national part and the last four digits of a number.
 *
 * The country and the first digits stay readable, so an operator can tell which number was meant
 * without the record holding it.
 */
#[Package('framework')]
final class PhoneNumberMasker implements PhoneNumberMaskerInterface
{
    /**
     * A national part shorter than this keeps its last four digits only - there is no middle to hide.
     */
    private const MIN_NATIONAL_DIGITS = 7;

    private const VISIBLE_HEAD = 3;
    private const VISIBLE_TAIL = 4;

    public function mask(PhoneNumber $phone): string
    {
        $dialCode = $phone->dialCode();
        $nationalNumber = $phone->nationalNumber();

        if (\strlen($nationalNumber) < self::MIN_NATIONAL_DIGITS) {
            return $this->maskTail($phone->e164());
        }

        return '+' . $dialCode
            . substr($nationalNumber, 0, self::VISIBLE_HEAD)
            . '****'
            . substr($nationalNumber, -self::VISIBLE_TAIL);
    }

    /**
     * A number the data cannot split has no middle to hide, its tail is masked instead.
     */
    private function maskTail(string $e164): string
    {
        if (\strlen($e164) <= self::MIN_NATIONAL_DIGITS) {
            return $e164;
        }

        return substr($e164, 0, self::VISIBLE_TAIL) . '****' . substr($e164, -self::VISIBLE_TAIL);
    }
}
