<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * Generates opaque approval numbers until a workflow number range is configured.
 * The full UUID is required: UUIDv7 prefixes contain timestamps, not random entropy.
 */
#[Package('framework')]
final class WorkflowNumberGenerator
{
    public function next(): string
    {
        return 'WF' . date('Ymd') . strtoupper(Uuid::randomHex());
    }
}
