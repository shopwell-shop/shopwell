<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Specification;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum WorkflowNodeKind: string
{
    case START = 'start';
    case ACTIVITY = 'activity';
    case END = 'end';
}
