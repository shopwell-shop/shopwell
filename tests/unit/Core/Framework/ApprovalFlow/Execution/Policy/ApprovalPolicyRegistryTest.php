<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\ApprovalPolicyHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\ApprovalPolicyRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalPolicyRegistry::class)]
class ApprovalPolicyRegistryTest extends TestCase
{
    public function testResolvesAnExtensionWithoutAddingACorePolicy(): void
    {
        $policy = new MajorityApprovalPolicy();
        $registry = new ApprovalPolicyRegistry([$policy]);

        static::assertSame($policy, $registry->get('example:majority', '1.0.0'));
        static::assertSame(ApprovalOutcome::Approved, $policy->evaluate([
            ApprovalTaskStatus::Approved,
            ApprovalTaskStatus::Approved,
            ApprovalTaskStatus::Pending,
        ]));
    }

    public function testFirstLookupKeepsItsRequestedIdentityWhileLoadingOtherPolicies(): void
    {
        $majority = new MajorityApprovalPolicy();
        $other = new class extends MajorityApprovalPolicy {
            public function getType(): string
            {
                return 'example:other';
            }
        };
        $registry = new ApprovalPolicyRegistry([$majority, $other]);

        static::assertSame($majority, $registry->get('example:majority', '1.0.0'));
        static::assertSame($majority, $registry->get('example:majority', '1.0.0'));
    }

    public function testRejectsAnInvalidExtensionIdentity(): void
    {
        $policy = new class extends MajorityApprovalPolicy {
            public function getType(): string
            {
                return 'invalid';
            }
        };

        $this->expectExceptionObject(ApprovalFlowException::invalidApprovalPolicy($policy::class));
        (new ApprovalPolicyRegistry([$policy]))->get('invalid', '1.0.0');
    }

    public function testDoesNotSubstituteAnotherPolicyVersion(): void
    {
        $registry = new ApprovalPolicyRegistry([new MajorityApprovalPolicy()]);

        $this->expectExceptionObject(ApprovalFlowException::approvalPolicyNotFound('example:majority', '2.0.0'));
        $registry->get('example:majority', '2.0.0');
    }

    public function testRejectsDuplicatePoliciesWithoutPublishingAPartialCatalogue(): void
    {
        $registry = new ApprovalPolicyRegistry([new MajorityApprovalPolicy(), new MajorityApprovalPolicy()]);

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $registry->get('example:majority', '1.0.0');
                static::fail('An ambiguous policy must not become available after a failed load.');
            } catch (ApprovalFlowException $exception) {
                static::assertSame(ApprovalFlowException::DUPLICATE_APPROVAL_POLICY, $exception->getErrorCode());
            }
        }
    }
}

/**
 * @internal
 */
class MajorityApprovalPolicy implements ApprovalPolicyHandler
{
    public function getType(): string
    {
        return 'example:majority';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function evaluate(array $statuses): ?ApprovalOutcome
    {
        $threshold = intdiv(\count($statuses), 2) + 1;
        if (\count(array_filter($statuses, static fn (ApprovalTaskStatus $status): bool => $status === ApprovalTaskStatus::Approved)) >= $threshold) {
            return ApprovalOutcome::Approved;
        }
        if (\count(array_filter($statuses, static fn (ApprovalTaskStatus $status): bool => $status === ApprovalTaskStatus::Rejected)) >= $threshold) {
            return ApprovalOutcome::Rejected;
        }

        return null;
    }
}
