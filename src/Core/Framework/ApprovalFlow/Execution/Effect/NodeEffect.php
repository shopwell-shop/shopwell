<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\Log\Package;

/**
 * A typed request to the execution kernel, not permission to mutate runtime state.
 * Executors return the framework's immutable effect values; declarations permit their kinds.
 * The entry boundary rejects effect implementations the kernel cannot interpret.
 */
#[Package('framework')]
interface NodeEffect
{
    public function getType(): NodeEffectType;
}
