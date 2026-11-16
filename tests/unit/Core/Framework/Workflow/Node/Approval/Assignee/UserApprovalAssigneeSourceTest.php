<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\UserApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserApprovalAssigneeSource::class)]
class UserApprovalAssigneeSourceTest extends TestCase
{
    public function testNormalizesSingleUserAndExposesStaticPublicationChecks(): void
    {
        $source = new UserApprovalAssigneeSource(multiple: false);
        $assignment = $source->compile(['userId' => '0123456789ABCDEF0123456789ABCDEF']);

        static::assertSame('core:user', $source->getName());
        static::assertFalse($assignment->multiple);
        static::assertSame('user', $assignment->type);
        static::assertSame(['0123456789abcdef0123456789abcdef'], $assignment->values);
        static::assertSame($assignment->values, $assignment->knownUserIds);
    }

    public function testPreservesParticipantOrderForSequentialApproval(): void
    {
        $source = new UserApprovalAssigneeSource(multiple: true);
        $ids = ['abcdef0123456789abcdef0123456789', '0123456789abcdef0123456789abcdef'];
        $assignment = $source->compile(['userIds' => $ids]);

        static::assertSame('core:users', $source->getName());
        static::assertTrue($assignment->multiple);
        static::assertSame($ids, $assignment->values);
        static::assertSame($ids, $assignment->knownUserIds);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidSelections')]
    public function testRejectsInvalidSelections(array $configuration): void
    {
        $this->expectException(WorkflowException::class);

        (new UserApprovalAssigneeSource(multiple: true))->compile($configuration);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidSelections(): iterable
    {
        yield 'empty' => [['userIds' => []]];
        yield 'scalar' => [['userIds' => '0123456789abcdef0123456789abcdef']];
        yield 'invalid ID' => [['userIds' => ['invalid']]];
        yield 'duplicate after normalization' => [['userIds' => ['0123456789abcdef0123456789abcdef', '0123456789ABCDEF0123456789ABCDEF']]];
        yield 'unrecognized option' => [['userIds' => ['0123456789abcdef0123456789abcdef'], 'fallback' => true]];
    }
}
