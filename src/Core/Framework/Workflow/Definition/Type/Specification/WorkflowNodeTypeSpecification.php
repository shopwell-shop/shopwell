<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Specification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal Experimental definition contract; registration does not imply executable support.
 */
#[Package('framework')]
final readonly class WorkflowNodeTypeSpecification
{
    /**
     * @param list<string> $inputPorts
     * @param list<string> $outputPorts
     */
    public function __construct(
        public string $name,
        public int $version,
        public WorkflowNodeKind $kind,
        public array $inputPorts,
        public array $outputPorts,
    ) {
        if (!preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $name) || $version < 1) {
            throw WorkflowException::invalidNodeType($name);
        }

        foreach ([$inputPorts, $outputPorts] as $ports) {
            if (!array_is_list($ports) || \count(array_unique($ports)) !== \count($ports)) {
                throw WorkflowException::invalidNodeType($name);
            }

            foreach ($ports as $port) {
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $port)) {
                    throw WorkflowException::invalidNodeType($name);
                }
            }
        }

        if (($kind === WorkflowNodeKind::START && $inputPorts !== [])
            || ($kind === WorkflowNodeKind::END && $outputPorts !== [])
            || ($kind !== WorkflowNodeKind::START && $inputPorts === [])
            || ($kind !== WorkflowNodeKind::END && $outputPorts === [])
        ) {
            throw WorkflowException::invalidNodeType($name);
        }
    }
}
