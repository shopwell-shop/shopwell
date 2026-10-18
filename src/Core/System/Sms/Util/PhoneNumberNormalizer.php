<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * Brings a phone number into the e.164 form the providers expect and derives the country of a
 * number, which the routing uses to pick between the domestic and the international gateways.
 *
 * @internal
 */
#[Package('framework')]
final class PhoneNumberNormalizer
{
    /**
     * The dial codes of the countries the platform is usually sold in, the longest match wins so a
     * number that starts with 852 is Hong Kong and not China plus two digits.
     */
    private const DIAL_CODES = [
        '971' => 'AE', '966' => 'SA', '234' => 'NG', '852' => 'HK', '853' => 'MO', '886' => 'TW',
        '86' => 'CN', '81' => 'JP', '82' => 'KR', '65' => 'SG', '60' => 'MY', '66' => 'TH',
        '84' => 'VN', '62' => 'ID', '91' => 'IN', '44' => 'GB', '49' => 'DE', '33' => 'FR',
        '39' => 'IT', '34' => 'ES', '31' => 'NL', '61' => 'AU', '64' => 'NZ', '55' => 'BR',
        '27' => 'ZA', '20' => 'EG', '7' => 'RU', '1' => 'US',
    ];

    private const DEFAULT_COUNTRY = 'CN';
    private const MIN_DIGITS = 7;
    private const MAX_DIGITS = 15;

    public function __construct(private readonly SystemConfigService $configService)
    {
    }

    /**
     * Accepts the local notations a customer may have entered (13800000000, 013800000000,
     * 0086-138-0000-0000) and returns the e.164 form (+8613800000000).
     *
     * @throws SmsException when the number cannot be made sense of, before a provider is called
     */
    public function toE164(string $phone, ?string $salesChannelId = null): string
    {
        $normalized = preg_replace('/[\s()\-.\/]+/', '', $phone) ?? '';
        $normalized = trim($normalized);

        if ($normalized === '') {
            throw SmsException::invalidPhoneNumber($phone);
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        }

        if (!str_starts_with($normalized, '+')) {
            $dialCode = $this->dialCodeOf($this->defaultCountry($salesChannelId));
            $national = ltrim($normalized, '0');

            if ($national === '') {
                throw SmsException::invalidPhoneNumber($phone);
            }

            // the number already carries its dial code, e.g. 8613800000000
            $normalized = str_starts_with($national, $dialCode) ? '+' . $national : '+' . $dialCode . $national;
        }

        $digits = substr($normalized, 1);

        if (!ctype_digit($digits) || \strlen($digits) < self::MIN_DIGITS || \strlen($digits) > self::MAX_DIGITS) {
            throw SmsException::invalidPhoneNumber($phone);
        }

        return $normalized;
    }

    /**
     * The ISO code of the country of an e.164 number, null when the dial code is not known.
     */
    public function countryCode(string $e164): ?string
    {
        $dialCode = $this->matchDialCode($e164);

        return $dialCode === null ? null : self::DIAL_CODES[$dialCode];
    }

    /**
     * A masked form of the number for the delivery record, e.g. +86138****0000.
     */
    public function mask(string $e164): string
    {
        $dialCode = $this->matchDialCode($e164);

        if ($dialCode === null) {
            return $this->maskTail($e164);
        }

        $national = substr($e164, 1 + \strlen($dialCode));

        if (\strlen($national) < self::MIN_DIGITS) {
            return $this->maskTail($e164);
        }

        return '+' . $dialCode . substr($national, 0, 3) . '****' . substr($national, -4);
    }

    /**
     * A stable hash of the number, used to deduplicate and to audit without keeping the plain number.
     *
     * It is deliberately not salted: the record has to be groupable by number across messages, and a
     * number with its handful of digits could not be protected by a salt in the first place.
     */
    public function hash(string $e164): string
    {
        return Hasher::hash($e164);
    }

    /**
     * The estimated number of billable parts of a message. A longer message is split by the providers
     * at the encoding boundaries, a part holds 160 characters of the gsm alphabet or 70 of anything else.
     */
    public function countSegments(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $length = mb_strlen($text);
        $gsm = mb_check_encoding($text, 'ASCII') && preg_match('/^[\x20-\x7E\r\n]*$/', $text) === 1;

        if ($gsm) {
            return (int) max(1, (int) ceil(\strlen($text) / 160));
        }

        return (int) max(1, (int) ceil($length / 70));
    }

    private function matchDialCode(string $e164): ?string
    {
        $digits = ltrim($e164, '+');

        // the keys of the map are cast back, the ones that look like a number are int in php
        $dialCodes = array_map(strval(...), array_keys(self::DIAL_CODES));
        usort($dialCodes, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        foreach ($dialCodes as $dialCode) {
            if (str_starts_with($digits, $dialCode)) {
                return $dialCode;
            }
        }

        return null;
    }

    private function dialCodeOf(string $country): string
    {
        $dialCode = array_search($country, self::DIAL_CODES, true);

        return $dialCode === false ? '86' : (string) $dialCode;
    }

    private function defaultCountry(?string $salesChannelId): string
    {
        $country = strtoupper(trim($this->configService->getString('core.smsSettings.defaultCountry', $salesChannelId)));

        return $country === '' ? self::DEFAULT_COUNTRY : $country;
    }

    private function maskTail(string $e164): string
    {
        $length = \strlen($e164);

        if ($length <= 7) {
            return $e164;
        }

        return substr($e164, 0, 4) . '****' . substr($e164, -4);
    }
}
