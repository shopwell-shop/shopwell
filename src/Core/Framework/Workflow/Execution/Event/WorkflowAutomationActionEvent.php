<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Extension seam for connectors, notifications, data actions and subprocess runners.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowAutomationActionEvent extends Event
{
    public bool $handled = false;

    /**
     * @var array<string, mixed>
     */
    public array $output = [];

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly GraphNode $node,
        public readonly WorkflowExecutionContext $context,
        public readonly string $action,
        public readonly array $input,
    ) {
    }

    /**
     * @param array<string, mixed> $output
     */
    public function complete(array $output = []): void
    {
        $this->handled = true;
        $this->output = $output;
    }
}
