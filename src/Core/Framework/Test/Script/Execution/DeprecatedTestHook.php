<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Script\Execution;

use Shopwell\Core\Framework\Script\Execution\DeprecatedHook;

/**
 * @internal
 */
class DeprecatedTestHook extends TestHook implements DeprecatedHook
{
    public static function getDeprecationNotice(): string
    {
        return 'Hook "DeprecatedTestHook" is obviously deprecated.';
    }
}
