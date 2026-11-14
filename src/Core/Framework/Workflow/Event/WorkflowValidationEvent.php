<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal Extensions add node configuration and business diagnostics after structural validation.
 */
#[Package('framework')]
final class WorkflowValidationEvent extends Event
{
    /**
     * @var list<WorkflowViolation>
     */
    private array $violations = [];

    public function __construct(public readonly WorkflowDocument $document)
    {
    }

    public function addViolation(WorkflowViolation $violation): void
    {
        $this->violations[] = $violation;
    }

    /**
     * @return list<WorkflowViolation>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
