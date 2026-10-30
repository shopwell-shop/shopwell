<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * The e.164 form of a number, together with the country the routing uses as a hint.
 *
 * The hint is a hint: a number of a country the dial code data does not list keeps a null country and
 * is routed as GLOBAL. Only the syntax of the number is an invariant - the shop never decides whether
 * a number exists, belongs to a customer or is reachable.
 *
 * The dial code of such a number is empty as well, because the length of an unknown prefix is not
 * known - dialCode() and nationalNumber() are the parts the data was able to name, and their
 * concatenation is always the number without its plus.
 *
 * It is the parameter and return type of the public PhoneNumberNormalizerInterface and
 * PhoneNumberMaskerInterface, so it is a public contract and not internal.
 */
#[Package('framework')]
final readonly class PhoneNumber
{
    /**
     * The ITU limits of an e.164 number, a shorter or longer one is not a number the module sends to.
     */
    private const MIN_DIGITS = 7;
    private const MAX_DIGITS = 15;

    private const ISO_PATTERN = '/^[A-Z]{2}\z/';

    private function __construct(
        private string $e164,
        private ?string $countryIso,
        private string $nationalNumber,
        private string $dialCode,
    ) {
    }

    /**
     * Builds the number out of the parts a normaliser has read, refusing parts that do not describe
     * one number.
     *
     * @param string|null $countryIso the ISO code the dial code data knows, null when it knows none
     * @param string $dialCode the dial code without the plus, empty when the data knows none
     *
     * @throws SmsException when the parts do not describe one e.164 number
     */
    public static function fromE164(string $e164, ?string $countryIso, string $dialCode): self
    {
        $digits = str_starts_with($e164, '+') ? substr($e164, 1) : '';

        if (!ctype_digit($digits) || \strlen($digits) < self::MIN_DIGITS || \strlen($digits) > self::MAX_DIGITS) {
            throw SmsException::invalidPhoneNumber($e164);
        }

        // the code has to be the prefix of the number, otherwise the parts tell two different stories
        if (!str_starts_with($digits, $dialCode) || $digits === $dialCode) {
            throw SmsException::invalidPhoneNumber($e164);
        }

        // a country always carries a dial code, a number without one cannot name a country
        if ($countryIso !== null && ($dialCode === '' || preg_match(self::ISO_PATTERN, $countryIso) !== 1)) {
            throw SmsException::invalidPhoneNumber($e164);
        }

        return new self($e164, $countryIso, substr($digits, \strlen($dialCode)), $dialCode);
    }

    /**
     * The canonical form, e.g. +8613800000000.
     */
    public function e164(): string
    {
        return $this->e164;
    }

    /**
     * The ISO code of the country of the number, null when the dial code data does not know it.
     */
    public function countryIso(): ?string
    {
        return $this->countryIso;
    }

    /**
     * The part of the number behind its dial code, empty dial code means the whole number.
     */
    public function nationalNumber(): string
    {
        return $this->nationalNumber;
    }

    /**
     * The dial code without the plus, empty when the dial code data does not know it.
     */
    public function dialCode(): string
    {
        return $this->dialCode;
    }
}
