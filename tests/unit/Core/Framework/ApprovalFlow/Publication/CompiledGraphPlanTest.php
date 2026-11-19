<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledGraphPlan;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationSnapshot;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompiledGraphPlan::class)]
class CompiledGraphPlanTest extends TestCase
{
    public function testRetainsTheSnapshotAndHashesTheCompletePlanBytes(): void
    {
        $snapshot = new GraphPublicationSnapshot(graphJson: '{"id":"graph"}', contractJson: '{"id":"contract"}');
        $first = new CompiledGraphPlan($snapshot, '{"graphHash":"first"}');
        $second = new CompiledGraphPlan($snapshot, '{"graphHash":"second"}');

        static::assertSame($snapshot, $first->snapshot);
        static::assertSame('{"graphHash":"first"}', $first->json);
        static::assertSame(hash('sha256', $first->json), $first->hash);
        static::assertNotSame($first->hash, $second->hash);
    }
}
