<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalHierarchy;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalHierarchy::class)]
class ApprovalHierarchyTest extends TestCase
{
    public function testAnEmptyConfigurationKeepsTheImmediateLevel(): void
    {
        $hierarchy = ApprovalHierarchy::fromConfiguration([]);

        static::assertSame(1, $hierarchy->level);
        static::assertFalse($hierarchy->continuous);
    }

    public function testContinuousApprovalIncludesEveryLevelUpToTheSelectedEndpoint(): void
    {
        $hierarchy = ApprovalHierarchy::fromConfiguration(['level' => 3, 'continuous' => true]);

        static::assertSame(3, $hierarchy->level);
        static::assertTrue($hierarchy->continuous);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidHierarchyConfiguration(array $configuration): void
    {
        $this->expectException(WorkflowException::class);

        ApprovalHierarchy::fromConfiguration($configuration);
    }

    public function testRejectsAnExplicitNullInsteadOfUsingTheDefaultLevel(): void
    {
        $this->expectException(WorkflowException::class);

        ApprovalHierarchy::fromConfiguration(null);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'zero is not an ancestor level' => [['level' => 0]];
        yield 'traversal is bounded to twenty levels' => [['level' => 21]];
        yield 'numeric strings are not configuration integers' => [['level' => '2']];
        yield 'present null must not select the default level' => [['level' => null]];
        yield 'continuous is a boolean' => [['continuous' => 1]];
        yield 'unknown keys must not be silently discarded' => [['skipMissing' => true]];
    }
}
