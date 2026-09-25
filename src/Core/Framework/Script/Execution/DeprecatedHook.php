<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface DeprecatedHook
{
    public static function getDeprecationNotice(): string;
}
