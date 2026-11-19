<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Task;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalDecisionEvaluator;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalPolicy;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalDecisionEvaluator::class)]
class ApprovalDecisionEvaluatorTest extends TestCase
{
    /**
     * @param non-empty-list<ApprovalTaskStatus> $statuses
     */
    #[DataProvider('groupDecisions')]
    public function testEvaluatesTheFrozenGroup(ApprovalPolicy $policy, array $statuses, ?ApprovalOutcome $expected): void
    {
        static::assertSame($expected, (new ApprovalDecisionEvaluator())->evaluate($policy, $statuses));
    }

    public static function groupDecisions(): iterable
    {
        yield 'single pending' => [ApprovalPolicy::Single, [ApprovalTaskStatus::Pending], null];
        yield 'single approves' => [ApprovalPolicy::Single, [ApprovalTaskStatus::Approved], ApprovalOutcome::Approved];
        yield 'single rejects' => [ApprovalPolicy::Single, [ApprovalTaskStatus::Rejected], ApprovalOutcome::Rejected];
        yield 'all waits for the remaining vote' => [ApprovalPolicy::All, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Pending], null];
        yield 'all approves' => [ApprovalPolicy::All, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Approved], ApprovalOutcome::Approved];
        yield 'all vetoes' => [ApprovalPolicy::All, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Rejected, ApprovalTaskStatus::Pending], ApprovalOutcome::Rejected];
        yield 'any waits for a vote' => [ApprovalPolicy::Any, [ApprovalTaskStatus::Pending, ApprovalTaskStatus::Pending], null];
        yield 'any approves' => [ApprovalPolicy::Any, [ApprovalTaskStatus::Pending, ApprovalTaskStatus::Approved], ApprovalOutcome::Approved];
        yield 'any vetoes' => [ApprovalPolicy::Any, [ApprovalTaskStatus::Pending, ApprovalTaskStatus::Rejected], ApprovalOutcome::Rejected];
    }
}
