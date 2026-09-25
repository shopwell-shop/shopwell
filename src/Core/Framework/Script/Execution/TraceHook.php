<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * Only to be used by "dummy" hooks for the sole purpose of tracing
 *
 * @internal
 */
#[Package('framework')]
abstract class TraceHook extends Hook
{
}
