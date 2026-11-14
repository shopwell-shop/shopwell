<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Diagnostics;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicyRegistry;
use Shopwell\Core\Framework\Workflow\Event\WorkflowValidationEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal Enforces graph integrity; topology and business rules are extensions.
 */
#[Package('framework')]
final readonly class WorkflowGraphDiagnostics
{
    public function __construct(
        private WorkflowNodeTypeResolver $types,
        private WorkflowGraphPolicyRegistry $policies,
        private EventDispatcherInterface $events,
    ) {
    }

    /**
     * @return list<WorkflowViolation>
     */
    public function analyze(WorkflowDocument $document): array
    {
        $policy = $this->policies->get($document->policy);
        $violations = [];
        $nodes = [];
        $resolvedTypes = [];
        $connectionIds = [];
        foreach ($document->nodes as $index => $node) {
            if (isset($nodes[$node->id])) {
                $violations[] = new WorkflowViolation('duplicate_node_id', '/nodes/' . $index . '/id', $node->id);
            }
            $nodes[$node->id] = $node;
            $type = $this->types->resolve($node);
            if ($type === null) {
                $violations[] = new WorkflowViolation('unknown_node_type', '/nodes/' . $index . '/type', $node->id);
            } else {
                $resolvedTypes[$node->id] = $type;
            }
        }
        foreach ($document->connections as $index => $edge) {
            $path = '/connections/' . $index;
            if (isset($connectionIds[$edge->id])) {
                $violations[] = new WorkflowViolation('duplicate_connection_id', $path . '/id');
            }
            $connectionIds[$edge->id] = true;
            $source = $nodes[$edge->source] ?? null;
            $target = $nodes[$edge->target] ?? null;
            if ($source === null) {
                $violations[] = new WorkflowViolation('unknown_source', $path . '/source');
            }
            if ($target === null) {
                $violations[] = new WorkflowViolation('unknown_target', $path . '/target');
            }
            if ($source === null || $target === null) {
                continue;
            }
            $sourceType = $resolvedTypes[$source->id] ?? null;
            $targetType = $resolvedTypes[$target->id] ?? null;
            if ($sourceType !== null && !\in_array($edge->sourcePort, $sourceType->outputPorts, true)) {
                $violations[] = new WorkflowViolation('unknown_output_port', $path . '/sourcePort', $source->id);
            }
            if ($targetType !== null && !\in_array($edge->targetPort, $targetType->inputPorts, true)) {
                $violations[] = new WorkflowViolation('unknown_input_port', $path . '/targetPort', $target->id);
            }
        }
        if ($violations !== []) {
            return $violations;
        }

        $violations = $policy->analyze($document, $resolvedTypes);
        $event = new WorkflowValidationEvent($document);
        $this->events->dispatch($event);

        return [...$violations, ...$event->getViolations()];
    }
}
