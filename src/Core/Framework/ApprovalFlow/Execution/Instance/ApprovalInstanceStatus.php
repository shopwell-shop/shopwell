<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalInstanceStatus: string
{
    case Draft = 'draft';
    case Running = 'running';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Terminated = 'terminated';
    case Withdrawn = 'withdrawn';
}
