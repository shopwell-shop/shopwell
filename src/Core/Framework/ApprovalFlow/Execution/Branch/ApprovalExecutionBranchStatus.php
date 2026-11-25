<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Branch;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalExecutionBranchStatus: string
{
    case Pending = 'pending';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
}
