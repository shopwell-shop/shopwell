<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;

/**
 * @internal
 */
#[Package('framework')]
final class FirstDecisionApprovalMode extends AbstractApprovalMode
{
    public function getName(): string
    {
        return 'core:any';
    }

    public function getType(): string
    {
        return WorkflowCountersignTypes::FIRST_DECISION;
    }

    public function resolve(ApprovalVotes $votes, array $configuration): CounterSignResult
    {
        if ($votes->rejected > 0) {
            return CounterSignResult::rejected($votes->agreed, $votes->rejected, $votes->total);
        }

        if ($votes->agreed > 0) {
            return CounterSignResult::agreed($votes->agreed, $votes->rejected, $votes->total);
        }

        return CounterSignResult::pending($votes->agreed, $votes->rejected, $votes->total);
    }
}
