<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\Log\Package;

/**
 * Request a process outcome; the runtime must still verify token and scope completion.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class CompleteEffect implements NodeEffect
{
    public function __construct(public ApprovalOutcome $outcome)
    {
    }

    public function getType(): NodeEffectType
    {
        return NodeEffectType::Complete;
    }
}
