<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Mode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\SequentialApprovalMode;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SequentialApprovalMode::class)]
class SequentialApprovalModeTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testAppliesTheDocumentedDecisionRule(int $agreed, int $rejected, int $total, string $expected): void
    {
        $votes = new ApprovalVotes([], $agreed, $rejected, $total, 3);

        $result = (new SequentialApprovalMode())->resolve($votes, []);

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
        yield 'completed participants pass' => [3, 0, 3, 'agreed'];
        yield 'a rejection rejects immediately' => [1, 1, 2, 'rejected'];
        yield 'uncreated participants keep the node waiting' => [1, 0, 1, 'pending'];
    }
}
