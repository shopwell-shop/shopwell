<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;

/**
 * @internal
 */
#[Package('framework')]
final class SingleApprovalMode extends AbstractApprovalMode
{
    public function getName(): string
    {
        return 'core:single';
    }

    public function getType(): string
    {
        return WorkflowCountersignTypes::SINGLE;
    }

    public function supportsMultipleAssignees(): bool
    {
        return false;
    }

    public function resolve(ApprovalVotes $votes, array $configuration): CounterSignResult
    {
        if ($votes->agreed === 1) {
            return CounterSignResult::agreed($votes->agreed, $votes->rejected, $votes->total);
        }

        if ($votes->rejected === 1) {
            return CounterSignResult::rejected($votes->agreed, $votes->rejected, $votes->total);
        }

        return CounterSignResult::pending($votes->agreed, $votes->rejected, $votes->total);
    }
}
