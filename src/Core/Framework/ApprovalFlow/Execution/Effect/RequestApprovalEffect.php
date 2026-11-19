<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * Request task planning from configured candidates; user eligibility and task creation remain runtime responsibilities.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class RequestApprovalEffect
{
    public function __construct(public ApprovalNodeConfiguration $configuration)
    {
    }
}
