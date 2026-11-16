<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Graph;

use Shopwell\Core\Framework\Log\Package;

/**
 * An immutable contract edge; conditions belong to edges and remain unchanged until evaluation.
 *
 * @internal
 */
#[Package('framework')]
final class GraphEdge
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sourceNodeId,
        public readonly string $targetNodeId,
        public readonly array $properties,
    ) {
    }

    /**
     * Return the condition expression, or null for an absent or whitespace-only default branch.
     */
    public function expr(): ?string
    {
        $expr = $this->properties['expr'] ?? null;

        if (!\is_string($expr)) {
            return null;
        }

        $expr = trim($expr);

        return $expr === '' ? null : $expr;
    }

    /**
     * @return array{id: string, sourceNodeId: string, targetNodeId: string, properties: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sourceNodeId' => $this->sourceNodeId,
            'targetNodeId' => $this->targetNodeId,
            'properties' => $this->properties,
        ];
    }
}
