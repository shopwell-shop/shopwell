<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\CompiledApprovalConfiguration;

/**
 * Override publication-time selection; the selected implementation and version are saved in the graph.
 * Runtime decisions never reapply this override to an existing instance.
 *
 * @extends Extension<CompiledApprovalConfiguration>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CompileApprovalConfigurationExtension extends Extension
{
    public const NAME = 'workflow.approval.compile-configuration';

    /**
     * @internal
     *
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public array $configuration,
        public readonly string $path,
        public readonly string $nodeId,
    ) {
    }
}
