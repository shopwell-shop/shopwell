<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * Runtime configuration and ordered design ports produced by a node compiler.
 * Terminal ports must connect to an end node; the executor completes them directly.
 */
#[Package('framework')]
final readonly class CompiledApprovalNode
{
    /**
     * @param array<string, mixed> $properties
     * @param list<string> $inputPorts
     * @param array<string, array<string, mixed>> $outputPorts
     * @param list<string> $terminalPorts
     */
    public function __construct(
        public string $type,
        public array $properties = [],
        public array $inputPorts = ['in'],
        public array $outputPorts = ['out' => []],
        public array $terminalPorts = [],
    ) {
        if ($type === '') {
            throw WorkflowException::invalidDocument('/compilation/type', 'A runtime node type is required.');
        }

        $this->validatePorts($inputPorts);
        $this->validatePorts([...array_keys($outputPorts), ...$terminalPorts]);
    }

    /**
     * @param list<string> $ports
     */
    private function validatePorts(array $ports): void
    {
        if (!array_is_list($ports) || \count(array_unique($ports)) !== \count($ports)) {
            throw WorkflowException::invalidDocument('/compilation/ports', 'Ports must be unique names.');
        }

        foreach ($ports as $port) {
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $port) !== 1) {
                throw WorkflowException::invalidDocument('/compilation/ports', 'Port names are invalid.');
            }
        }
    }
}
