<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\AllApprovalPolicy;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\AnyApprovalPolicy;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\ApprovalPolicyRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\SingleApprovalPolicy;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SingleApprovalPolicy::class)]
#[CoversClass(AllApprovalPolicy::class)]
#[CoversClass(AnyApprovalPolicy::class)]
class CoreApprovalPolicyTest extends TestCase
{
    /**
     * @param non-empty-list<ApprovalTaskStatus> $statuses
     */
    #[DataProvider('groupDecisions')]
    public function testEvaluatesTheFrozenGroup(string $policy, array $statuses, ?ApprovalOutcome $expected): void
    {
        static::assertSame($expected, (new ApprovalPolicyRegistry([new SingleApprovalPolicy(), new AllApprovalPolicy(), new AnyApprovalPolicy()]))->get($policy, '1.0.0')->evaluate($statuses));
    }

    public static function groupDecisions(): iterable
    {
        yield 'single pending' => [ApprovalNodeConfiguration::SINGLE, [ApprovalTaskStatus::Pending], null];
        yield 'single approves' => [ApprovalNodeConfiguration::SINGLE, [ApprovalTaskStatus::Approved], ApprovalOutcome::Approved];
        yield 'single rejects' => [ApprovalNodeConfiguration::SINGLE, [ApprovalTaskStatus::Rejected], ApprovalOutcome::Rejected];
        yield 'all waits for the remaining vote' => [ApprovalNodeConfiguration::ALL, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Pending], null];
        yield 'all approves' => [ApprovalNodeConfiguration::ALL, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Approved], ApprovalOutcome::Approved];
        yield 'all vetoes' => [ApprovalNodeConfiguration::ALL, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Rejected, ApprovalTaskStatus::Pending], ApprovalOutcome::Rejected];
        yield 'any waits for a vote' => [ApprovalNodeConfiguration::ANY, [ApprovalTaskStatus::Pending, ApprovalTaskStatus::Pending], null];
        yield 'any approves' => [ApprovalNodeConfiguration::ANY, [ApprovalTaskStatus::Pending, ApprovalTaskStatus::Approved], ApprovalOutcome::Approved];
        yield 'any vetoes' => [ApprovalNodeConfiguration::ANY, [ApprovalTaskStatus::Pending, ApprovalTaskStatus::Rejected], ApprovalOutcome::Rejected];
    }
}
