<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\BuiltInWorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\WorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\StructuredWorkflowGraphPolicy;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicyRegistry;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowGraphDiagnostics::class)]
#[CoversClass(StructuredWorkflowGraphPolicy::class)]
class WorkflowGraphDiagnosticsTest extends TestCase
{
    private WorkflowGraphDiagnostics $validator;

    protected function setUp(): void
    {
        $provider = static::createStub(WorkflowNodeTypeLoader::class);
        $provider->method('load')->willReturn([
            new WorkflowNodeTypeSpecification('example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']),
        ]);
        $registry = new WorkflowNodeTypeRegistry([new BuiltInWorkflowNodeTypeLoader(), $provider]);
        $this->validator = new WorkflowGraphDiagnostics(new WorkflowNodeTypeResolver($registry, new EventDispatcher()), new WorkflowGraphPolicyRegistry([new StructuredWorkflowGraphPolicy()]), new EventDispatcher());
    }

    public function testCustomActivityRequiresNoApprovalConcepts(): void
    {
        static::assertSame([], $this->validator->analyze($this->document()));
    }

    public function testIncompleteDraftReportsMissingBoundaries(): void
    {
        $diagnostics = $this->validator->analyze(new WorkflowDocument([], []));

        static::assertSame(['start_count', 'end_missing'], array_column($diagnostics, 'code'));
    }

    public function testUnknownTypeVersionRemainsInDocumentAndGetsPreciseDiagnostic(): void
    {
        $document = $this->document(typeVersion: 2);
        $diagnostics = $this->validator->analyze($document);

        static::assertContains('unknown_node_type', array_column($diagnostics, 'code'));
        $unknown = array_values(array_filter($diagnostics, static fn ($diagnostic) => $diagnostic->code === 'unknown_node_type'))[0];
        static::assertSame('/nodes/1/type', $unknown->path);
        static::assertSame('task', $unknown->nodeId);
        static::assertSame(2, $document->nodes[1]->typeVersion);
    }

    public function testDuplicateNodeIdsAreRejectedBeforeBuildingAdjacency(): void
    {
        $document = $this->document();
        $document = new WorkflowDocument([...$document->nodes, $document->nodes[1]], $document->connections);

        static::assertContains('duplicate_node_id', array_column($this->validator->analyze($document), 'code'));
    }

    public function testDuplicateConnectionIdsAreRejected(): void
    {
        $document = $this->document();
        $document = new WorkflowDocument($document->nodes, [...$document->connections, $document->connections[0]]);

        static::assertContains('duplicate_connection_id', array_column($this->validator->analyze($document), 'code'));
    }

    public function testMissingEndpointAndUnknownPortAreDiagnosed(): void
    {
        $document = $this->document();
        $document = new WorkflowDocument($document->nodes, [
            new WorkflowConnection('first', 'start', 'missing', 'task', 'in'),
            new WorkflowConnection('second', 'task', 'out', 'deleted', 'in'),
        ]);

        $codes = array_column($this->validator->analyze($document), 'code');
        static::assertContains('unknown_output_port', $codes);
        static::assertContains('unknown_target', $codes);
    }

    public function testCycleAndUnreachableEndAreRejected(): void
    {
        $document = $this->document();
        $document = new WorkflowDocument($document->nodes, [
            new WorkflowConnection('first', 'start', 'out', 'task', 'in'),
            new WorkflowConnection('loop', 'task', 'out', 'task', 'in'),
        ]);

        $codes = array_column($this->validator->analyze($document), 'code');
        static::assertContains('cycle', $codes);
        static::assertContains('unreachable_node', $codes);
        static::assertContains('no_path_to_end', $codes);
    }

    public function testDisconnectedActivityCannotBePublished(): void
    {
        $document = $this->document();
        $document = new WorkflowDocument([...$document->nodes, new WorkflowNode('orphan', 'example:task', 1)], $document->connections);

        $codes = array_column($this->validator->analyze($document), 'code');
        static::assertContains('unreachable_node', $codes);
        static::assertContains('unconnected_output', $codes);
    }

    public function testOneOutputCannotSilentlyBecomeAParallelFork(): void
    {
        $document = $this->document();
        $document = new WorkflowDocument($document->nodes, [...$document->connections, new WorkflowConnection('fork', 'start', 'out', 'end', 'in')]);

        static::assertContains('multiple_output_connections', array_column($this->validator->analyze($document), 'code'));
    }

    private function document(int $typeVersion = 1): WorkflowDocument
    {
        return new WorkflowDocument([
            new WorkflowNode('start', 'core:start', 1),
            new WorkflowNode('task', 'example:task', $typeVersion),
            new WorkflowNode('end', 'core:end', 1),
        ], [
            new WorkflowConnection('first', 'start', 'out', 'task', 'in'),
            new WorkflowConnection('second', 'task', 'out', 'end', 'in'),
        ]);
    }
}
