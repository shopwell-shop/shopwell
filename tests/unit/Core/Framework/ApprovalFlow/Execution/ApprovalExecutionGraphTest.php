<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionGraph;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalExecutionGraph::class)]
class ApprovalExecutionGraphTest extends TestCase
{
    public function testTheStartRouteUsesThePublishedIndex(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);

        static::assertSame(['connectionId' => 'start-review', 'nextNodeId' => 'review'], $index->startRoute());
    }

    public function testAnAmbiguousStartCannotChooseAnArbitraryConnection(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $graph->connections[] = clone $graph->connections[0];
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $this->expectExceptionObject(ApprovalFlowException::unsupportedStartRoute('release'));

        $index->startRoute();
    }

    public function testPublishedIndexesAreDetachedFromTheDecodedGraph(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $graph->nodes[1]->id = 'changed';
        $graph->connections[0]->target = 'changed';
        $graph->connections[0]->sourcePort = 'changed';

        static::assertSame([['connectionId' => 'start-review', 'nextNodeId' => 'review']], $index->successors('start', ['out']));
        $index->validateScope('start', 'review', $index->successors('start'), []);
    }

    public function testAnUndeclaredOutputCannotSilentlyEndTheRoute(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $this->expectExceptionObject(ApprovalFlowException::unsupportedNodeRoute('release', 'start'));

        $index->successors('start', ['missing']);
    }

    public function testAScopeCannotReuseItsEnclosingReturnBoundary(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('start', 'A distinct return boundary inside the current region is required.'));

        $index->validateScope('start', 'end', $index->successors('start'), ['end']);
    }

    public function testEverySelectedPathMustReachItsOwnReturnBoundary(): void
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $graph->connections[0]->target = 'end';
        $index = new ApprovalExecutionGraph('release', 'start', 'end', $graph);
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('start', 'Selected branches must be disjoint and return before an enclosing boundary or process end.'));

        $index->validateScope('start', 'review', $index->successors('start'), []);
    }
}
