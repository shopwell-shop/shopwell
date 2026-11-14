<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Registry;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\WorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowNodeTypeRegistry
{
    /**
     * @var array<string, array<int, WorkflowNodeTypeSpecification>>
     */
    private array $types = [];

    /**
     * @param iterable<WorkflowNodeTypeLoader> $loaders
     */
    public function __construct(iterable $loaders)
    {
        foreach ($loaders as $loader) {
            foreach ($loader->load() as $type) {
                if (isset($this->types[$type->name][$type->version])) {
                    throw WorkflowException::duplicateNodeType($type->name, $type->version);
                }

                $this->types[$type->name][$type->version] = $type;
            }
        }
    }

    /**
     * @return list<WorkflowNodeTypeSpecification>
     */
    public function all(): array
    {
        $types = [];
        foreach ($this->types as $versions) {
            foreach ($versions as $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function find(string $name, int $version): ?WorkflowNodeTypeSpecification
    {
        return $this->types[$name][$version] ?? null;
    }
}
