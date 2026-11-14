<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Connection;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WorkflowConnection
{
    public function __construct(
        public string $id,
        public string $source,
        public string $sourcePort,
        public string $target,
        public string $targetPort,
    ) {
    }
}
