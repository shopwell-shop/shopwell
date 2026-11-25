<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\Log\Package;

/**
 * Retain this execution until another arrival changes its scope snapshot.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WaitEffect implements NodeEffect
{
    public function getType(): NodeEffectType
    {
        return NodeEffectType::Wait;
    }
}
