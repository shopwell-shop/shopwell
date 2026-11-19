<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledGraphPlan;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledNodePlan;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationSnapshot;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompiledNodePlan::class)]
class CompiledNodePlanTest extends TestCase
{
    public function testRetainsItsGraphPlanAndHashesTheFrozenNodeBytes(): void
    {
        $snapshot = new GraphPublicationSnapshot(graphJson: '{"id":"graph"}', contractJson: '{}');
        $graphPlan = new CompiledGraphPlan($snapshot, '{"snapshotHash":"source"}');
        $first = new CompiledNodePlan($graphPlan, '{"graphPlanHash":"first"}');
        $second = new CompiledNodePlan($graphPlan, '{"graphPlanHash":"second"}');

        static::assertSame($graphPlan, $first->graphPlan);
        static::assertSame('{"graphPlanHash":"first"}', $first->json);
        static::assertSame(hash('sha256', $first->json), $first->hash);
        static::assertNotSame($first->hash, $second->hash);
    }
}
