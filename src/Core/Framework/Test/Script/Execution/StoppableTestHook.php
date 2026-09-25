<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Script\Execution;

use Shopwell\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopwell\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;

/**
 * @internal
 */
class StoppableTestHook extends TestHook implements StoppableHook
{
    use StoppableHookTrait;
}
