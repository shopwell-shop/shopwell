<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\Log\Package;

/**
 * Request routing from the current node; the runtime owns edge selection and token consumption.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class AdvanceEffect
{
}
