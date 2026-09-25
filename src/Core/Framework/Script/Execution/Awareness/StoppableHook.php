<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution\Awareness;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface StoppableHook
{
    public function stopPropagation(): void;

    public function isPropagationStopped(): bool;
}
