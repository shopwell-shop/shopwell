<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\BuiltInWorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\StructuredWorkflowGraphPolicy;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicyRegistry;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Publication\EndWorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\StartWorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowNodeCompilerRegistry;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowCompiler::class)]
class WorkflowCompilerTest extends TestCase
{
    public function testCompilesAWorkflowWithoutApprovalNodesOrApprovalCompiler(): void
    {
        $document = new WorkflowDocument(
            [new WorkflowNode('start', 'core:start', 1), new WorkflowNode('end', 'core:end', 1)],
            [new WorkflowConnection('begin', 'start', 'out', 'end', 'in')],
        );
        $compiled = $this->compiler()->compile($document, Context::createDefaultContext());

        static::assertSame(['snaker:start', 'snaker:end'], array_column($compiled->toArray()['nodes'], 'type'));
        static::assertSame('core:start', $compiled->singleStartNode()->property('workflowNodeType'));
        static::assertSame('out', $compiled->edges()[0]->properties['sourcePort']);
        static::assertSame([], $compiled->singleStartNode()->property('inputSchema'));
    }

    public function testRejectsAVisualNodeThatHasNoExecutableCompiler(): void
    {
        $document = new WorkflowDocument(
            [new WorkflowNode('start', 'core:start', 1), new WorkflowNode('action', 'core:action', 1), new WorkflowNode('end', 'core:end', 1)],
            [new WorkflowConnection('a', 'start', 'out', 'action', 'in'), new WorkflowConnection('b', 'action', 'out', 'end', 'in')],
        );

        $this->expectExceptionObject(WorkflowException::publicationRejected('node_compiler_unavailable'));
        $this->compiler()->compile($document, Context::createDefaultContext());
    }

    public function testRejectsACompilerWhoseRuntimeExecutorIsMissing(): void
    {
        $compiler = new class implements WorkflowNodeCompiler {
            public function getNodeType(): string
            {
                return 'core:action';
            }

            public function getNodeVersion(): int
            {
                return 1;
            }

            public function compile(WorkflowNode $node, WorkflowDocument $document, Context $context): GraphNode
            {
                return new GraphNode($node->id, 'extension:missing-executor', 0, 0, []);
            }
        };
        $document = new WorkflowDocument(
            [new WorkflowNode('start', 'core:start', 1), new WorkflowNode('action', 'core:action', 1), new WorkflowNode('end', 'core:end', 1)],
            [new WorkflowConnection('a', 'start', 'out', 'action', 'in'), new WorkflowConnection('b', 'action', 'out', 'end', 'in')],
        );

        $this->expectExceptionObject(WorkflowException::publicationRejected('workflow_execution_unavailable'));
        $this->compiler([$compiler])->compile($document, Context::createDefaultContext());
    }

    #[DataProvider('runtimeIdentityLengths')]
    public function testChecksRuntimeIdentitiesBeforePublication(string $subject, int $length): void
    {
        $startId = $subject === 'node' ? str_repeat('n', $length) : 'start';
        $edgeId = $subject === 'connection' ? str_repeat('e', $length) : 'begin';
        $document = new WorkflowDocument(
            [new WorkflowNode($startId, 'core:start', 1), new WorkflowNode('end', 'core:end', 1)],
            [new WorkflowConnection($edgeId, $startId, 'out', 'end', 'in')],
        );

        if ($length <= 64) {
            static::assertSame($startId, $this->compiler()->compile($document, Context::createDefaultContext())->singleStartNode()->id);

            return;
        }

        try {
            $this->compiler()->compile($document, Context::createDefaultContext());
            static::fail('An oversized runtime identity was published');
        } catch (WorkflowException $exception) {
            $violation = $exception->getParameters()['violations'][0];
            static::assertInstanceOf(WorkflowViolation::class, $violation);
            static::assertSame('runtime_identifier_too_long', $violation->code);
            static::assertSame($subject === 'node' ? '/nodes/0/id' : '/connections/0/id', $violation->path);
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function runtimeIdentityLengths(): iterable
    {
        yield 'node at storage limit' => ['node', 64];
        yield 'node over storage limit' => ['node', 65];
        yield 'node at editor limit' => ['node', 128];
        yield 'node beyond editor limit' => ['node', 129];
        yield 'connection at storage limit' => ['connection', 64];
        yield 'connection over storage limit' => ['connection', 65];
        yield 'connection at editor limit' => ['connection', 128];
        yield 'connection beyond editor limit' => ['connection', 129];
    }

    /**
     * @param list<WorkflowNodeCompiler> $additional
     */
    private function compiler(array $additional = []): WorkflowCompiler
    {
        $events = new EventDispatcher();
        $types = new WorkflowNodeTypeRegistry([new BuiltInWorkflowNodeTypeLoader()]);
        $diagnostics = new WorkflowGraphDiagnostics(new WorkflowNodeTypeResolver($types, $events), new WorkflowGraphPolicyRegistry([new StructuredWorkflowGraphPolicy()]), $events);
        $compilers = new WorkflowNodeCompilerRegistry([new StartWorkflowNodeCompiler(new WorkflowInputSchema()), new EndWorkflowNodeCompiler(), ...$additional]);
        $validator = new WorkflowGraphValidator([new ShapeRule(new NodeExecutorRegistry([new StartExecutor(), new EndExecutor()]))]);

        return new WorkflowCompiler($compilers, $diagnostics, $validator);
    }
}
