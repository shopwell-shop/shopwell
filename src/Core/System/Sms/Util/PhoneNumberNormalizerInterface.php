<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * Canonicalises the written form of a phone number.
 *
 * It is the single place that decides whether the module can make sense of a number, every other
 * consumer works on the value object it answers - which is why an extension that replaces the
 * implementation replaces the rules of the whole component.
 */
#[Package('framework')]
interface PhoneNumberNormalizerInterface
{
    /**
     * Canonicalises the written form of a number, nothing else.
     *
     * A number in international notation (+4915112345678 or 004915112345678) is canonicalised on its
     * own. A number without a country code is only canonicalised against the country the caller
     * passes - the module has no default country of its own and never guesses one, because a guess
     * sends the message to a stranger.
     *
     * @param string|null $countryIso the ISO code the caller knows, e.g. from the address of the
     *                                customer; null when the caller does not know it
     *
     * @throws SmsException when the number is not canonicalisable
     */
    public function normalize(string $phone, ?string $countryIso = null): PhoneNumber;
}
