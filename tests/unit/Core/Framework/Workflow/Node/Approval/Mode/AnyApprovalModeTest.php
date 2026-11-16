<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Mode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AnyApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AnyApprovalMode::class)]
class AnyApprovalModeTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testAppliesTheDocumentedDecisionRule(int $agreed, int $rejected, int $total, string $expected): void
    {
        $votes = new ApprovalVotes([], $agreed, $rejected, $total, null);

        $result = (new AnyApprovalMode())->resolve($votes, []);

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
        yield 'an approval passes despite a rejection' => [1, 1, 3, 'agreed'];
        yield 'every rejection rejects' => [0, 3, 3, 'rejected'];
        yield 'a rejection with pending votes waits' => [0, 1, 3, 'pending'];
    }
}
