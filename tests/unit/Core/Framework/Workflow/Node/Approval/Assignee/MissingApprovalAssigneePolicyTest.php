<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\MissingApprovalAssigneePolicy;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingApprovalAssigneePolicy::class)]
class MissingApprovalAssigneePolicyTest extends TestCase
{
    public function testTheDefaultBlocksInsteadOfApprovingWithoutAReviewer(): void
    {
        $policy = MissingApprovalAssigneePolicy::fromConfiguration([]);

        static::assertSame('block', $policy->action);
        static::assertNull($policy->userId);
        static::assertSame(['action' => 'block'], $policy->configuration());
    }

    public function testAReplacementReviewerMustBeExplicitAndHasANormalizedIdentity(): void
    {
        $policy = MissingApprovalAssigneePolicy::fromConfiguration([
            'action' => 'assign',
            'userId' => 'ABCDEF0123456789ABCDEF0123456789',
        ]);

        static::assertSame('abcdef0123456789abcdef0123456789', $policy->userId);
        static::assertSame(['action' => 'assign', 'userId' => $policy->userId], $policy->configuration());
    }

    public function testAutomaticDecisionsRequireAnExplicitAction(): void
    {
        static::assertSame('approve', MissingApprovalAssigneePolicy::fromConfiguration(['action' => 'approve'])->action);
        static::assertSame('reject', MissingApprovalAssigneePolicy::fromConfiguration(['action' => 'reject'])->action);
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedPolicies(mixed $configuration): void
    {
        $this->expectException(WorkflowException::class);

        MissingApprovalAssigneePolicy::fromConfiguration($configuration);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'explicit null is invalid' => [null];
        yield 'unknown action is invalid' => [['action' => 'ignore']];
        yield 'assign requires a reviewer' => [['action' => 'assign']];
        yield 'assign requires a valid identity' => [['action' => 'assign', 'userId' => 'missing']];
        yield 'non-assignment must not hide a reviewer' => [['action' => 'approve', 'userId' => 'abcdef0123456789abcdef0123456789']];
        yield 'unknown options are invalid' => [['action' => 'block', 'administrator' => true]];
        yield 'an action is required for a nonempty configuration' => [['userId' => 'abcdef0123456789abcdef0123456789']];
        yield 'lists are not configuration maps' => [['approve']];
    }
}
