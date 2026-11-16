<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Mode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\FirstDecisionApprovalMode;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FirstDecisionApprovalMode::class)]
class FirstDecisionApprovalModeTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testAppliesTheDocumentedDecisionRule(int $agreed, int $rejected, int $total, string $expected): void
    {
        $votes = new ApprovalVotes([], $agreed, $rejected, $total, null);

        $result = (new FirstDecisionApprovalMode())->resolve($votes, []);

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
        yield 'the first approval passes' => [1, 0, 3, 'agreed'];
        yield 'the first rejection rejects' => [0, 1, 3, 'rejected'];
        yield 'no decisions keep the node waiting' => [0, 0, 3, 'pending'];
    }
}
