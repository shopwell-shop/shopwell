<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Node;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WorkflowNode
{
    /**
     * Definition configuration is a JSON value, not a DAL write payload.
     * A future persistence adapter must store its JSON representation without losing numeric object keys.
     *
     * @param array<string, mixed>|\stdClass $config Opaque configuration, including unknown node types.
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $typeVersion,
        public array|\stdClass $config = [],
    ) {
    }
}
