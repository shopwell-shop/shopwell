<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationPreflight;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationSnapshot;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphPlanCompiler::class)]
class GraphPlanCompilerTest extends TestCase
{
    private GraphPlanCompiler $compiler;

    protected function setUp(): void
    {
        $container = $this->container();
        $container->getDefinition(GraphPlanCompiler::class)->setPublic(true);
        $container->compile();
        $compiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $compiler);
        $this->compiler = $compiler;
    }

    public function testCompilesGraphIndexesThroughActualServiceAssembly(): void
    {
        $draft = new DraftGraph(GraphFixture::json());

        $compiled = $this->compiler->compile($draft);

        $plan = json_decode($compiled->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertSame('core:graph-plan', $plan['compilerId']);
        static::assertSame('1.0.0', $plan['compilerVersion']);
        static::assertSame($compiled->snapshot->graphHash, $plan['graphHash']);
        static::assertSame($compiled->snapshot->hash, $plan['snapshotHash']);
        static::assertSame('start', $plan['startNodeId']);
        static::assertSame('end', $plan['endNodeId']);
        static::assertSame([
            'start' => ['index' => 0, 'incoming' => [], 'outgoing' => ['start-review']],
            'review' => ['index' => 1, 'incoming' => ['start-review'], 'outgoing' => ['review-end']],
            'end' => ['index' => 2, 'incoming' => ['review-end'], 'outgoing' => []],
        ], $plan['nodes']);
        static::assertSame(['start-review' => 0, 'review-end' => 1], $plan['connections']);
        static::assertSame(['start', 'review', 'end'], $plan['topologicalOrder']);
        static::assertSame(hash('sha256', $compiled->json), $compiled->hash);
        static::assertSame(GraphFixture::json(), $draft->json);
        static::assertArrayNotHasKey('config', $plan);
    }

    #[DataProvider('invalidGraphProvider')]
    public function testCompilationCannotBypassPreflight(string $search, string $replacement): void
    {
        $draft = new DraftGraph(str_replace($search, $replacement, GraphFixture::json()));
        $violation = GraphFixture::diagnostics()->analyze($draft)->violations[0];
        $this->expectExceptionObject(ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message));

        $this->compiler->compile($draft);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidGraphProvider(): iterable
    {
        yield 'incomplete configuration remains a draft' => ['"outcome": "approved"', ''];
        yield 'duplicate keys are rejected before indexing' => ['"schemaVersion":', '"id":"hidden", "schemaVersion":'];
        yield 'missing exact declaration cannot produce a plan' => ['"typeVersion": "1.0.0"', '"typeVersion": "2.0.0"'];
        yield 'dangling references cannot become index entries' => ['"target": "review"', '"target": "gone"'];
        yield 'cycles cannot produce a partial topology' => ['"target": "end"', '"target": "review"'];
    }

    #[DataProvider('unsupportedIdentityProvider')]
    public function testLeadingNullIdentitiesAreRejectedBeforeIndexing(string $search, string $replacement, string $path): void
    {
        $draft = new DraftGraph(str_replace($search, $replacement, GraphFixture::json()));

        try {
            $this->compiler->compile($draft);
            static::fail('An identity that cannot be represented as an object key must be rejected.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::PUBLICATION_GRAPH_INVALID, $exception->getErrorCode());
            static::assertSame($path, $exception->getParameters()['path']);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unsupportedIdentityProvider(): iterable
    {
        yield 'node identity starts with a null character' => ['"review"', '"\\u0000review"', '/nodes/1/id'];
        yield 'connection identity starts with a null character' => ['"start-review"', '"\\u0000start-review"', '/connections/0/id'];
    }

    /**
     * @param list<string> $nodeIds
     * @param list<string> $connectionIds
     */
    #[DataProvider('nodeIdentityProvider')]
    public function testIndexMapsPreserveStringIdentities(array $nodeIds, array $connectionIds): void
    {
        $ids = [...$nodeIds, ...$connectionIds];
        $replacements = array_map(static fn (string $id): string => json_encode($id, \JSON_THROW_ON_ERROR), $ids);
        $json = str_replace(['"start"', '"review"', '"end"', '"start-review"', '"review-end"'], $replacements, GraphFixture::json());

        $compiled = $this->compiler->compile(new DraftGraph($json));

        $plan = json_decode($compiled->json, flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $plan);
        static::assertInstanceOf(\stdClass::class, $plan->nodes);
        static::assertInstanceOf(\stdClass::class, $plan->connections);
        static::assertSame($nodeIds, $plan->topologicalOrder);
        static::assertSame($nodeIds[0], $plan->startNodeId);
        static::assertSame($nodeIds[2], $plan->endNodeId);
        foreach ($nodeIds as $index => $id) {
            static::assertTrue(property_exists($plan->nodes, $id));
            $node = $plan->nodes->{$id};
            static::assertInstanceOf(\stdClass::class, $node);
            static::assertSame($index, $node->index);
        }
        foreach ($connectionIds as $index => $id) {
            static::assertSame($index, $plan->connections->{$id});
        }
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function nodeIdentityProvider(): iterable
    {
        yield 'consecutive numeric IDs stay object keys and string references' => [['0', '1', '2'], ['0', '1']];
        yield 'embedded null characters remain opaque identities' => [["start\0id", "review\0id", "end\0id"], ["start\0review", "review\0end"]];
        yield 'numeric spellings do not merge' => [['1', '01', '001'], ['1', '01']];
        yield 'Unicode and special property names are opaque identities' => [['__proto__', '审/批~1', 'constructor'], ['边/一', '边~二']];
    }

    public function testEquivalentGraphFormattingProducesIdenticalPlanBytes(): void
    {
        $first = $this->compiler->compile(new DraftGraph(GraphFixture::json()));

        $second = $this->compiler->compile(new DraftGraph(str_replace('    ', '', GraphFixture::json())));

        static::assertSame($first->json, $second->json);
        static::assertSame($first->hash, $second->hash);
        static::assertSame($first->snapshot->hash, $second->snapshot->hash);
    }

    public function testCompiledPlanMatchesItsVersionedWireSchema(): void
    {
        $schema = (new Filesystem())->readFile(\dirname(__DIR__, 6) . '/src/Core/Framework/ApprovalFlow/Resources/schema/plan/1.0.0/graph-plan.schema.json');
        $compiled = $this->compiler->compile(new DraftGraph(GraphFixture::json()));

        $violations = (new GraphSchemaValidator())->validate(json_decode($compiled->json, flags: \JSON_THROW_ON_ERROR), $schema);

        static::assertSame([], $violations);
    }

    public function testChangedDeclarationsInvalidateThePlanEvenWhenGraphBytesStayFixed(): void
    {
        $first = $this->compiler->compile(new DraftGraph(GraphFixture::json()));
        $container = $this->container();
        $schema = (new Filesystem())->readFile(\dirname(__DIR__, 6) . '/src/Core/Framework/ApprovalFlow/Resources/schema/graph/1.0.0/approval-graph.schema.json');
        $container->getDefinition(GraphPublicationPreflight::class)->setArgument(2, $schema . "\n");
        $container->getDefinition(GraphPlanCompiler::class)->setPublic(true);
        $container->compile();
        $compiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $compiler);

        $second = $compiler->compile(new DraftGraph(GraphFixture::json()));

        static::assertSame($first->snapshot->graphHash, $second->snapshot->graphHash);
        static::assertNotSame($first->snapshot->hash, $second->snapshot->hash);
        static::assertNotSame($first->hash, $second->hash);
    }

    public function testBusinessNodesParticipateWithoutACompilerBranchInTheFramework(): void
    {
        $container = $this->container();
        $container->register('finance.approval.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', __DIR__ . '/../Graph/Type/Registry/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');
        $container->getDefinition(GraphPlanCompiler::class)->setPublic(true);
        $container->compile();
        $compiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $compiler);
        $json = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        $compiled = $compiler->compile(new DraftGraph($json));

        $plan = json_decode($compiled->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertIsArray($plan['nodes']);
        static::assertSame(['index' => 1, 'incoming' => ['start-review'], 'outgoing' => ['review-end']], $plan['nodes']['review']);
        static::assertStringContainsString('"type":"finance:review"', $compiled->snapshot->graphJson);
        static::assertStringContainsString('"options":{}', $compiled->snapshot->graphJson);
        static::assertStringNotContainsString('finance:review', $compiled->json);
    }

    public function testBranchAndMergeIndexesKeepEveryEdgeAndTheirAuthoredOrder(): void
    {
        $payload = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $payload);
        static::assertIsArray($payload->nodes);
        static::assertInstanceOf(\stdClass::class, $payload->nodes[0]);
        static::assertInstanceOf(\stdClass::class, $payload->nodes[1]);
        $start = $payload->nodes[0];
        $start->ports = [self::port('out', 'output', cardinality: 'many')];
        $left = clone $payload->nodes[1];
        $left->id = 'left';
        $right = clone $payload->nodes[1];
        $right->id = 'right';
        $join = clone $payload->nodes[1];
        $join->id = 'join';
        $join->ports = [self::port('in', 'input', cardinality: 'many'), self::port('out', 'output')];
        $payload->nodes = [$start, $left, $right, $join, $payload->nodes[2]];
        static::assertInstanceOf(\stdClass::class, $payload->layout);
        $payload->layout->nodes = [];
        $payload->connections = [
            self::connection('start-right', 'start', 'right'),
            self::connection('start-left', 'start', 'left'),
            self::connection('left-join', 'left', 'join'),
            self::connection('right-join', 'right', 'join'),
            self::connection('join-end', 'join', 'end'),
        ];

        $compiled = $this->compiler->compile(new DraftGraph(json_encode($payload, \JSON_THROW_ON_ERROR)));

        $plan = json_decode($compiled->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertIsArray($plan['nodes']);
        static::assertSame(['index' => 0, 'incoming' => [], 'outgoing' => ['start-right', 'start-left']], $plan['nodes']['start']);
        static::assertSame(['index' => 3, 'incoming' => ['left-join', 'right-join'], 'outgoing' => ['join-end']], $plan['nodes']['join']);
        static::assertSame(['start', 'right', 'left', 'join', 'end'], $plan['topologicalOrder']);
        static::assertSame(['start-right' => 0, 'start-left' => 1, 'left-join' => 2, 'right-join' => 3, 'join-end' => 4], $plan['connections']);
    }

    public function testParallelConnectionsBetweenTheSameNodesAreNotCollapsed(): void
    {
        $payload = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $payload);
        static::assertIsArray($payload->nodes);
        static::assertInstanceOf(\stdClass::class, $payload->nodes[1]);
        static::assertInstanceOf(\stdClass::class, $payload->nodes[2]);
        $payload->nodes[1]->ports = [self::port('in', 'input'), self::port('out', 'output', cardinality: 'many')];
        $payload->nodes[2]->ports = [self::port('in', 'input', cardinality: 'many')];
        static::assertIsArray($payload->connections);
        $payload->connections[] = self::connection('another-review-end', 'review', 'end');

        $compiled = $this->compiler->compile(new DraftGraph(json_encode($payload, \JSON_THROW_ON_ERROR)));

        $plan = json_decode($compiled->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertIsArray($plan['nodes']);
        static::assertSame(['index' => 1, 'incoming' => ['start-review'], 'outgoing' => ['review-end', 'another-review-end']], $plan['nodes']['review']);
        static::assertSame(['index' => 2, 'incoming' => ['review-end', 'another-review-end'], 'outgoing' => []], $plan['nodes']['end']);
        static::assertSame(['start', 'review', 'end'], $plan['topologicalOrder']);
    }

    public function testAnIncompletePreflightResultIsAnInternalCompilerFault(): void
    {
        $draft = new DraftGraph(GraphFixture::json());
        $snapshot = new GraphPublicationSnapshot(str_replace('core:start', 'test:missing-start', $draft->json), contractJson: '{}');
        $preflight = static::createStub(GraphPublicationPreflight::class);
        $preflight->method('prepare')->willReturn($snapshot);
        $compiler = new GraphPlanCompiler($preflight, new GraphJsonDecoder());
        $this->expectExceptionObject(ApprovalFlowException::invalidGraphPlan('Preflight did not provide both boundary nodes.'));

        $compiler->compile($draft);
    }

    private static function port(string $id, string $direction, string $cardinality = 'one'): \stdClass
    {
        return (object) ['id' => $id, 'direction' => $direction, 'channel' => 'control', 'cardinality' => $cardinality];
    }

    private static function connection(string $id, string $source, string $target): \stdClass
    {
        return (object) [
            'id' => $id, 'source' => $source, 'target' => $target, 'sourcePort' => 'out', 'targetPort' => 'in',
            'condition' => (object) ['kind' => 'always'], 'extensions' => new \stdClass(),
        ];
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection'));
        $loader->load('approval-flow.php');

        return $container;
    }
}
