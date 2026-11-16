<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;

/**
 * @internal
 */
#[Package('framework')]
final class AllApprovalMode extends AbstractApprovalMode
{
    public function getName(): string
    {
        return 'core:all';
    }

    public function getType(): string
    {
        return WorkflowCountersignTypes::COUNTERSIGN;
    }

    public function resolve(ApprovalVotes $votes, array $configuration): CounterSignResult
    {
        if ($votes->rejected > 0) {
            return CounterSignResult::rejected($votes->agreed, $votes->rejected, $votes->total);
        }

        if ($votes->agreed === $votes->total) {
            return CounterSignResult::agreed($votes->agreed, $votes->rejected, $votes->total);
        }

        return CounterSignResult::pending($votes->agreed, $votes->rejected, $votes->total);
    }
}
