<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalTaskStatus: string
{
    case Pending = 'pending';
    case Blocked = 'blocked';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Blocked;
    }
}
