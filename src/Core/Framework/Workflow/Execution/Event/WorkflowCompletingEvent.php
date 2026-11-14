<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Business extensions may finalize their own instance state before generic completion.
 */
#[Package('framework')]
final class WorkflowCompletingEvent extends Event
{
    public bool $handled = false;

    public function __construct(public readonly string $result, public readonly WorkflowExecutionContext $context)
    {
    }
}
