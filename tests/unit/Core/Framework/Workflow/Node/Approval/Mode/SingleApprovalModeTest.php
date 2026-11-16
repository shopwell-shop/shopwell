<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Mode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\SingleApprovalMode;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SingleApprovalMode::class)]
class SingleApprovalModeTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testAppliesTheDocumentedDecisionRule(int $agreed, int $rejected, int $total, string $expected): void
    {
        $votes = new ApprovalVotes([], $agreed, $rejected, $total, null);

        $result = (new SingleApprovalMode())->resolve($votes, []);

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
        yield 'an approval passes' => [1, 0, 1, 'agreed'];
        yield 'a rejection rejects' => [0, 1, 1, 'rejected'];
        yield 'a pending task waits' => [0, 0, 1, 'pending'];
    }
}
