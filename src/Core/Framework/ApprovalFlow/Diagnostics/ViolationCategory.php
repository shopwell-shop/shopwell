<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Diagnostics;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ViolationCategory: string
{
    case Structure = 'structure';
    case Draft = 'draft';
    case Capability = 'capability';
}
