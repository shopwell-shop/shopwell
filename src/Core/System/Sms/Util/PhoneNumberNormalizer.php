<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * Brings a written phone number into the e.164 form and names the country the routing uses.
 *
 * It only judges the syntax of a number and never whether the number exists: a number of a country
 * the dial code data does not list is a valid number without a country, it is not a broken one.
 *
 * The country of a number written without its country code comes from the caller, because the module
 * has no default country of its own - a default would silently send the message of a customer abroad
 * to a number of the shop's home country.
 *
 * @internal
 */
#[Package('framework')]
final class PhoneNumberNormalizer implements PhoneNumberNormalizerInterface
{
    /**
     * The ITU limits of an e.164 number, a shorter or longer one cannot be delivered.
     */
    private const MIN_DIGITS = 7;
    private const MAX_DIGITS = 15;

    public function __construct(private readonly CountryDialCodeResolver $dialCodes)
    {
    }

    public function normalize(string $phone, ?string $countryIso = null): PhoneNumber
    {
        $written = trim(preg_replace('/[\s()\-.\/]+/', '', $phone) ?? '');

        if ($written === '') {
            throw SmsException::invalidPhoneNumber($phone);
        }

        // 00 is the international prefix of large parts of the world, e.164 writes it as a plus
        if (str_starts_with($written, '00')) {
            $written = '+' . substr($written, 2);
        }

        if (!str_starts_with($written, '+')) {
            $written = $this->withDialCode($written, $countryIso, $phone);
        }

        $digits = substr($written, 1);

        if (!ctype_digit($digits) || \strlen($digits) < self::MIN_DIGITS || \strlen($digits) > self::MAX_DIGITS) {
            throw SmsException::invalidPhoneNumber($phone);
        }

        // a prefix the data does not know keeps the number valid, it just names no country
        $dialCode = $this->dialCodes->matchPrefix($digits) ?? '';
        $iso = $dialCode === '' ? null : $this->dialCodes->isoOfDialCode($dialCode);

        return PhoneNumber::fromE164($written, $iso, $dialCode);
    }

    /**
     * Prefixes a number that was written without its country code, or refuses it when neither the
     * caller nor the number says which country it belongs to.
     */
    private function withDialCode(string $written, ?string $countryIso, string $phone): string
    {
        $dialCode = $countryIso === null ? null : $this->dialCodes->dialCodeOfIso($countryIso);

        if ($dialCode === null) {
            // neither the caller nor the number itself names a country the module knows, and it never
            // guesses one - a guess sends the message to a stranger
            throw SmsException::phoneNumberNotInternational($phone);
        }

        // a trunk prefix is no part of the number, 0138… and 138… are the same number of China
        $nationalNumber = ltrim($written, '0');

        if ($nationalNumber === '') {
            throw SmsException::invalidPhoneNumber($phone);
        }

        // the number already carries its dial code, e.g. 8613800000000 written for China
        if (str_starts_with($nationalNumber, $dialCode)) {
            return '+' . $nationalNumber;
        }

        return '+' . $dialCode . $nationalNumber;
    }
}
