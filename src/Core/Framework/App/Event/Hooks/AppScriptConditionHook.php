<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Event\Hooks;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\TraceHook;

/**
 * @internal
 */
#[Package('framework')]
class AppScriptConditionHook extends TraceHook
{
    public static function getServiceIds(): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'rule-conditions';
    }
}
