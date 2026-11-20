<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class ApprovalBusinessTypeRegistry
{
    /**
     * @var array<string, ApprovalBusinessTypeSpecification>|null
     */
    private ?array $types = null;

    /**
     * @internal
     *
     * @param iterable<ApprovalBusinessTypeLoader> $loaders
     */
    public function __construct(private readonly iterable $loaders)
    {
    }

    /**
     * @return array<string, ApprovalBusinessTypeSpecification>
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
                    throw ApprovalFlowException::duplicateBusinessType(
                        $specification->type,
                        $specification->version,
                        $types[$key]->source,
                        $specification->source,
                    );
                }
                $types[$key] = $specification;
            }
        }

        // A later loader failure must not leave earlier declarations visible.
        $this->types = $types;

        return $types;
    }

    public function get(string $type, string $version): ApprovalBusinessTypeSpecification
    {
        return $this->all()[$type . '@' . $version] ?? throw ApprovalFlowException::businessTypeNotFound($type, $version);
    }
}
