<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Diagnostics\Policy;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;

/**
 * @internal
 */
#[Package('framework')]
interface WorkflowGraphPolicy
{
    public function getName(): string;

    /**
     * Called after identity, endpoint and port checks succeed.
     *
     * @param array<string, WorkflowNodeTypeSpecification> $types Resolved types keyed by node ID.
     *
     * @return list<WorkflowViolation>
     */
    public function analyze(WorkflowDocument $document, array $types): array;
}
