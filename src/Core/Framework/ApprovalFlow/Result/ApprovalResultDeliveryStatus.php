<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ApprovalResultDeliveryStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
