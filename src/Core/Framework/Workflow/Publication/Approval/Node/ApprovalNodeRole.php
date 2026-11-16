<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;

/**
 * Structural responsibilities of a node in a sequential approval graph.
 */
#[Package('framework')]
enum ApprovalNodeRole
{
    case START;
    case APPROVAL;
    case ACTIVITY;
    case END;
}
