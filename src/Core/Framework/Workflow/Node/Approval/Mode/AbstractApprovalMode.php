<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * Shared defaults for the built-in configuration-free approval modes.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractApprovalMode implements ApprovalMode
{
    public function getVersion(): int
    {
        return 1;
    }

    public function supportsMultipleAssignees(): bool
    {
        return true;
    }

    public function isSequential(): bool
    {
        return false;
    }

    public function validateConfiguration(array $configuration): void
    {
        if ($configuration !== []) {
            throw WorkflowException::invalidDocument('/approval/config', 'This approval mode does not accept configuration.');
        }
    }
}
