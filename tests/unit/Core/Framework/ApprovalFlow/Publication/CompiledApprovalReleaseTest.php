<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledApprovalRelease;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledGraphPlan;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledNodePlan;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationSnapshot;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompiledApprovalRelease::class)]
class CompiledApprovalReleaseTest extends TestCase
{
    public function testHashBindsBothTheNodePlanAndDeploymentDependencies(): void
    {
        $snapshot = new GraphPublicationSnapshot(graphJson: '{"id":"graph"}', contractJson: '{}');
        $graphPlan = new CompiledGraphPlan($snapshot, '{"snapshotHash":"source"}');
        $nodePlan = new CompiledNodePlan($graphPlan, '{"graphPlanHash":"source"}');
        $release = new CompiledApprovalRelease($nodePlan, '{"artifact":"first"}');
        $changedArtifact = new CompiledApprovalRelease($nodePlan, '{"artifact":"second"}');
        $changedPlan = new CompiledApprovalRelease(new CompiledNodePlan($graphPlan, '{"graphPlanHash":"changed"}'), $release->dependenciesJson);

        static::assertSame($snapshot->graphJson, $release->graphJson);
        static::assertSame($nodePlan, $release->nodePlan);
        static::assertNotSame($release->hash, $changedArtifact->hash);
        static::assertNotSame($release->hash, $changedPlan->hash);
    }
}
