<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Identity and original write context of an already locked instance command.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalExecutionCommandContext
{
    public function __construct(
        public string $instanceId,
        public string $releaseId,
        public string $commandId,
        public Context $context,
        private ?string $executionId = null,
    ) {
    }

    public function requireExecutionId(): string
    {
        return $this->executionId ?? throw ApprovalFlowException::invalidInstanceCommand('This command requires a node execution target.');
    }
}
