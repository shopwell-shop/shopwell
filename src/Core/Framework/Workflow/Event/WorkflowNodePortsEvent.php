<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal Resolve instance-specific ports, such as configurable condition branches.
 */
#[Package('framework')]
final class WorkflowNodePortsEvent extends Event
{
    /**
     * @var list<string>
     */
    private array $inputPorts;

    /**
     * @var list<string>
     */
    private array $outputPorts;

    public function __construct(public readonly WorkflowNode $node, public readonly WorkflowNodeTypeSpecification $type)
    {
        $this->inputPorts = $type->inputPorts;
        $this->outputPorts = $type->outputPorts;
    }

    /** @param list<string> $inputPorts
     * @param list<string> $outputPorts
     */
    public function setPorts(array $inputPorts, array $outputPorts): void
    {
        $this->inputPorts = $inputPorts;
        $this->outputPorts = $outputPorts;
    }

    /**
     * @return list<string>
     */
    public function getInputPorts(): array
    {
        return $this->inputPorts;
    }

    /**
     * @return list<string>
     */
    public function getOutputPorts(): array
    {
        return $this->outputPorts;
    }
}
