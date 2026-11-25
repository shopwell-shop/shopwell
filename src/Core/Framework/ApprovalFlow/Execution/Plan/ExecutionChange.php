<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Plan;

use Shopwell\Core\Framework\Log\Package;

/**
 * A semantic runtime change, admitted by the plan and applied by the store.
 *
 * @internal
 */
#[Package('framework')]
interface ExecutionChange
{
}
