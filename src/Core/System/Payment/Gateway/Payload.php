<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * Reads typed values out of a decoded payload of a channel.
 *
 * Every interface answers in its own dialect - a json object with snake case keys, a form encoded body,
 * a nested `amount` object - but the guard in front of a value is always the same: a key that is absent
 * or holds nothing usable is a missing value and never an empty string or a zero. Reading a payload
 * through these helpers instead of open coding the guard keeps the adapters agreeing on what "the
 * interface did not send this" means.
 *
 * The helpers deliberately do not throw: a payload is untrusted input, and the adapter decides whether
 * a missing reference is an error or simply the other kind of notification.
 *
 * @internal
 */
#[Package('framework')]
final class Payload
{
    private function __construct()
    {
    }

    /**
     * The value of a key as a non empty string, or the default when it is absent or empty.
     *
     * @param array<array-key, mixed> $values
     */
    public static function string(array $values, string $key, ?string $default = null): ?string
    {
        $value = $values[$key] ?? null;

        return \is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    /**
     * The value of a key as an integer, or null when it is absent or not numeric.
     *
     * @param array<array-key, mixed> $values
     */
    public static function int(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        return \is_numeric($value) ? (int) $value : null;
    }

    /**
     * The value of a key as the nested payload it is, or an empty array when it is not one.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    public static function map(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return \is_array($value) ? $value : [];
    }
}
