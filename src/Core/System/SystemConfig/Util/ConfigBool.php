<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * Reads a stored system config value as the bool it was meant to be.
 *
 * `system:config:set core.wallet.enabled false` writes the *string* `"false"` unless `--json` is
 * passed - the CLI keeps the raw argument (see {@see \Shopwell\Core\System\SystemConfig\Command\ConfigSet::handleDecode()}).
 * A plain `(bool)` cast then reads `"false"` as enabled, so every flag read as a bool has to
 * normalize the string forms first.
 *
 * This is the one place that knows the string spellings of "off", so the settings classes and any
 * future flag share one truth instead of each inventing a cast.
 *
 * @final
 */
#[Package('framework')]
class ConfigBool
{
    /**
     * Every spelling a config row can hold for "switched off".
     */
    private const FALSY = ['false', '0', '', 'off', 'no'];

    /**
     * `null` means "the shop never saved this key" and stays the caller's decision - a missing
     * value is not the same as a saved `false`.
     */
    public static function normalize(mixed $value): bool
    {
        if (\is_string($value)) {
            return !\in_array(\strtolower(\trim($value)), self::FALSY, true);
        }

        return (bool) $value;
    }
}
