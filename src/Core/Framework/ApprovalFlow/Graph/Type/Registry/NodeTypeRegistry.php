<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class NodeTypeRegistry
{
    /**
     * @var array<string, NodeTypeSpecification>|null
     */
    private ?array $types = null;

    /**
     * @internal
     *
     * @param iterable<NodeTypeLoader> $loaders
     */
    public function __construct(private readonly iterable $loaders)
    {
    }

    /**
     * @return array<string, NodeTypeSpecification>
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }
        $types = [];
        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $key = $specification->type . '@' . $specification->version;
                if (isset($types[$key])) {
                    throw ApprovalFlowException::duplicateNodeType(
                        $specification->type,
                        $specification->version,
                        $types[$key]->source,
                        $specification->source,
                    );
                }
                $types[$key] = $specification;
            }
        }

        // Publish only a completely loaded catalogue; failed loads expose no partial state.
        $this->types = $types;

        return $types;
    }

    public function get(string $type, string $version): NodeTypeSpecification
    {
        return $this->all()[$type . '@' . $version] ?? throw ApprovalFlowException::nodeTypeNotFound($type, $version);
    }
}
