<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Graph;

use Shopwell\Core\Framework\Log\Package;

/**
 * An immutable node containing only workflow contract fields, excluding editor state.
 *
 * @internal
 */
#[Package('framework')]
final class GraphNode
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly float $x,
        public readonly float $y,
        public readonly array $properties,
    ) {
    }

    /**
     * Read a property without mutating the definition snapshot.
     */
    public function property(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * Use the node ID when its display name is missing or empty.
     */
    public function name(): string
    {
        $name = $this->property('name');

        return \is_string($name) && trim($name) !== '' ? $name : $this->id;
    }

    /**
     * @return array{id: string, type: string, x: float, y: float, properties: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'x' => $this->x,
            'y' => $this->y,
            'properties' => $this->properties,
        ];
    }
}
