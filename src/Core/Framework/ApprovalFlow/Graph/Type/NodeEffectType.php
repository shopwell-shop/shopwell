<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum NodeEffectType: string
{
    case Advance = 'advance';
    case RequestApproval = 'requestApproval';
    case Complete = 'complete';
}
