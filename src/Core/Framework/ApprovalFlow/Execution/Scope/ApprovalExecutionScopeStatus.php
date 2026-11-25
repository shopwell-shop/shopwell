<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Scope;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalExecutionScopeStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
