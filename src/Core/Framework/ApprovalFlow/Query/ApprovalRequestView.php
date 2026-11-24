<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Query;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalRequestView: string
{
    case Todo = 'todo';
    case Done = 'done';
    case Started = 'started';
}
