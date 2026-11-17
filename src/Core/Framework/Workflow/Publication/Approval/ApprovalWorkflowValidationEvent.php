<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Check live node prerequisites during publication and release revalidation.
 * Subscribers must remain read-only and reject unavailable capabilities with a domain exception.
 */
#[Package('framework')]
final class ApprovalWorkflowValidationEvent extends Event
{
    public function __construct(public readonly GraphData $graph, public readonly Context $context)
    {
    }
}
