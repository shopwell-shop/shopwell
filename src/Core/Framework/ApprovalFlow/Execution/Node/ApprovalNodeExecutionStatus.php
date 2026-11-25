<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Node;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalNodeExecutionStatus: string
{
    case Waiting = 'waiting';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case AwaitingSignal = 'awaiting_signal';
    case Cancelled = 'cancelled';
}
