<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Mode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AllApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AllApprovalMode::class)]
class AllApprovalModeTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testAppliesTheDocumentedDecisionRule(int $agreed, int $rejected, int $total, string $expected): void
    {
        $votes = new ApprovalVotes([], $agreed, $rejected, $total, null);

        $result = (new AllApprovalMode())->resolve($votes, []);

        static::assertSame($expected, $result->status());
        static::assertSame($agreed, $result->agreedCount());
        static::assertSame($rejected, $result->rejectedCount());
        static::assertSame($total, $result->total());
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function decisions(): iterable
    {
        yield 'all approvals pass' => [2, 0, 2, 'agreed'];
        yield 'a rejection rejects immediately' => [1, 1, 3, 'rejected'];
        yield 'pending participants keep the node waiting' => [1, 0, 2, 'pending'];
    }
}
