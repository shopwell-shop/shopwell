<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\PositionApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PositionApprovalAssigneeSource::class)]
class PositionApprovalAssigneeSourceTest extends TestCase
{
    public function testPreservesPositionOrderAndDefersDepartmentMembersUntilNodeEntry(): void
    {
        $ids = ['ABCDEF0123456789ABCDEF0123456789', '0123456789abcdef0123456789abcdef'];
        $source = new PositionApprovalAssigneeSource();
        $assignment = $source->compile(['positionIds' => $ids]);

        static::assertSame('core:positions', $source->getName());
        static::assertSame('position', $assignment->type);
        static::assertSame(array_map(strtolower(...), $ids), $assignment->values);
        static::assertTrue($assignment->multiple);
        static::assertSame([], $assignment->knownUserIds);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidPositions(array $configuration): void
    {
        $this->expectException(WorkflowException::class);

        (new PositionApprovalAssigneeSource())->compile($configuration);
    }

    public static function invalidConfigurations(): iterable
    {
        yield 'missing selection' => [[]];
        yield 'empty selection' => [['positionIds' => []]];
        yield 'invalid identifier' => [['positionIds' => ['broken']]];
        yield 'duplicate position' => [['positionIds' => ['0123456789abcdef0123456789abcdef', '0123456789ABCDEF0123456789ABCDEF']]];
        yield 'unsupported department override' => [['positionIds' => ['0123456789abcdef0123456789abcdef'], 'departmentId' => 'other']];
    }
}
