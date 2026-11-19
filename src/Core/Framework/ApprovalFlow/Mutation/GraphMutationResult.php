<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Mutation;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnosticsReport;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\Log\Package;

/**
 * Removed wire fragments retain complete configuration, mappings and extensions for an explicit loss review.
 * No persistence or confirmation is performed by a pure mutation.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class GraphMutationResult
{
    /**
     * @param list<string> $removedNodes
     * @param list<string> $removedConnections
     * @param list<string> $removedLayout
     */
    public function __construct(
        public DraftGraph $graph,
        public GraphDiagnosticsReport $diagnostics,
        public array $removedNodes = [],
        public array $removedConnections = [],
        public array $removedLayout = [],
    ) {
    }
}
