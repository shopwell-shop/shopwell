<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow;

use Shopwell\Core\Framework\Log\Package;

/**
 * Process outcomes are fixed framework values; business-specific reasons belong to the owning business module.
 */
#[Package('framework')]
enum ApprovalOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
