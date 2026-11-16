<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;

/**
 * Register with shopwell.workflow.approval.mode.
 * Keep old versions installed while published definitions or instances reference them.
 */
#[Package('framework')]
interface ApprovalMode
{
    /**
     * The namespaced identifier offered by the designer.
     */
    public function getName(): string;

    /**
     * The immutable execution identifier stored on approval tasks (at most 32 characters).
     */
    public function getType(): string;

    public function getVersion(): int;

    public function supportsMultipleAssignees(): bool;

    public function isSequential(): bool;

    /**
     * Reject invalid configuration before publication and execution.
     *
     * @param array<string, mixed> $configuration
     */
    public function validateConfiguration(array $configuration): void;

    /**
     * Pure calculation; task writes, authorization and locking remain owned by the approval service.
     *
     * @param array<string, mixed> $configuration
     */
    public function resolve(ApprovalVotes $votes, array $configuration): CounterSignResult;
}
