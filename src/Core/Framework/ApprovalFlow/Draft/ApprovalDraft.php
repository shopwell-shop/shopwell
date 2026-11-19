<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnosticsReport;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalDraft
{
    public function __construct(
        public string $definitionId,
        public int $revision,
        public DraftGraph $graph,
        public GraphDiagnosticsReport $diagnostics,
    ) {
    }
}
