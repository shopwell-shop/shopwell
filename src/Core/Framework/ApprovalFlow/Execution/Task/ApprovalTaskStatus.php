<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalTaskStatus: string
{
    case Pending = 'pending';
}
