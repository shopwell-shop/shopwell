<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only rely on the concrete implementations
 */
#[Package('framework')]
abstract class InterfaceHook extends Hook
{
    /**
     * Returns the hook for a specific function in this interface.
     */
    abstract public function getFunction(string $name): FunctionHook;

    /**
     * Services are defined in the function hooks
     */
    public static function getServiceIds(): array
    {
        return [];
    }
}
