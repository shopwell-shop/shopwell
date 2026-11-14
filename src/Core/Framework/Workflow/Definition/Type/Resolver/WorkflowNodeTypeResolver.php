<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Resolver;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Event\WorkflowNodePortsEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowNodeTypeResolver
{
    public function __construct(private WorkflowNodeTypeRegistry $types, private EventDispatcherInterface $events)
    {
    }

    public function resolve(WorkflowNode $node): ?WorkflowNodeTypeSpecification
    {
        $type = $this->types->find($node->type, $node->typeVersion);
        if ($type === null) {
            return null;
        }
        $event = new WorkflowNodePortsEvent($node, $type);
        $this->events->dispatch($event);

        return new WorkflowNodeTypeSpecification($type->name, $type->version, $type->kind, $event->getInputPorts(), $event->getOutputPorts());
    }
}
