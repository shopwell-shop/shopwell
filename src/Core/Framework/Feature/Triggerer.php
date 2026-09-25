<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Feature;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * Wrapper around the native trigger_* functions to make calls testable
 */
#[Package('framework')]
class Triggerer
{
    public function deprecation(string $package, string $version, string $message): void
    {
        trigger_deprecation($package, $version, $message);
    }

    public function error(string $message, int $errorLevel = \E_USER_NOTICE): void
    {
        trigger_error($message, $errorLevel);
    }
}
