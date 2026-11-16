<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures;

use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * A plugin fixture: no production compiler or executor knows this approval mode.
 *
 * @internal
 */
final class QuorumApprovalMode implements ApprovalMode
{
    public function __construct(private readonly int $version = 2)
    {
    }

    public function getName(): string
    {
        return 'example:quorum';
    }

    public function getType(): string
    {
        return 'example:quorum';
    }

    public function getVersion(): int
    {
        return $this->version;
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
        if (array_keys($configuration) !== ['required'] || !\is_int($configuration['required']) || $configuration['required'] < 1) {
            throw WorkflowException::invalidDocument('/required', 'A positive approval threshold is required.');
        }
    }

    public function resolve(ApprovalVotes $votes, array $configuration): CounterSignResult
    {
        if ($votes->agreed >= $configuration['required']) {
            return CounterSignResult::agreed($votes->agreed, $votes->rejected, $votes->total);
        }

        if ($votes->total - $votes->rejected < $configuration['required']) {
            return CounterSignResult::rejected($votes->agreed, $votes->rejected, $votes->total);
        }

        return CounterSignResult::pending($votes->agreed, $votes->rejected, $votes->total);
    }
}
