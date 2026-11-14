<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Diagnostics;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WorkflowViolation
{
    public function __construct(
        public string $code,
        public string $path,
        public ?string $nodeId = null,
    ) {
    }
}
