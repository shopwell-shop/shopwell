<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * Estimates how many billable parts a free text message is sent as.
 *
 * A provider splits a longer message at the encoding boundaries, a part holds 160 characters of the
 * gsm alphabet or 70 characters of anything else. The count is an estimate for the delivery record, it
 * is never read to decide whether a message may go out.
 */
#[Package('framework')]
final class SmsSegmentCounter
{
    private const GSM_CHARS_PER_SEGMENT = 160;
    private const UNICODE_CHARS_PER_SEGMENT = 70;

    public function count(string $text): int
    {
        // A message without text is not a billable part, a message with text is at least one.
        if ($text === '') {
            return 0;
        }

        $isGsm = mb_check_encoding($text, 'ASCII') && preg_match('/^[\x20-\x7E\r\n]*\z/', $text) === 1;
        $length = $isGsm ? \strlen($text) : mb_strlen($text);
        $perSegment = $isGsm ? self::GSM_CHARS_PER_SEGMENT : self::UNICODE_CHARS_PER_SEGMENT;

        return (int) ceil($length / $perSegment);
    }
}
