<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationPreflight;
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
#[CoversClass(GraphPublicationPreflight::class)]
class GraphPublicationPreflightTest extends TestCase
{
    private string $graphSchema;

    /**
     * @var array<string, NodeTypeSpecification>
     */
    private array $types;

    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $root = \dirname(__DIR__, 6) . '/src/Core/Framework/ApprovalFlow';
        $this->graphSchema = $filesystem->readFile($root . '/Resources/schema/graph/1.0.0/approval-graph.schema.json');
        $loader = new YamlNodeTypeLoader($filesystem, [new NodeTypeSourceDirectory('core', $root . '/Resources/node-types')]);
        $this->types = [];
        foreach ($loader->load() as $type) {
            $this->types[$type->type . '@' . $type->version] = $type;
        }
    }

    public function testCapturesTheCanonicalGraphAndItsExactUsedDeclarations(): void
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection'));
        $loader->load('approval-flow.php');
        static::assertTrue($container->hasDefinition(GraphPublicationPreflight::class));
        $container->getDefinition(GraphPublicationPreflight::class)->setPublic(true);
        $container->compile();
        $preflight = $container->get(GraphPublicationPreflight::class);
        static::assertInstanceOf(GraphPublicationPreflight::class, $preflight);
        $draft = new DraftGraph(GraphFixture::json());

        $snapshot = $preflight->prepare($draft);

        static::assertSame(GraphFixture::codec()->encodeDraft($draft), $snapshot->graphJson);
        static::assertSame(hash('sha256', $snapshot->graphJson), $snapshot->graphHash);
        static::assertSame(GraphFixture::json(), $draft->json);
        $contract = json_decode($snapshot->contractJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($contract);
        static::assertSame(['core:approval', 'core:end', 'core:start'], array_column($contract['nodeTypes'], 'type'));
        static::assertSame($this->graphSchema, $contract['graphSchema']);
    }

    #[DataProvider('invalidGraphProvider')]
    public function testRejectsInvalidOrIncompleteGraphsWithoutChangingTheirSource(string $search, string $replacement): void
    {
        $json = str_replace($search, $replacement, GraphFixture::json());
        $draft = new DraftGraph($json);
        $violation = GraphFixture::diagnostics()->analyze($draft)->violations[0];
        $this->expectExceptionObject(ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message));

        try {
            $this->preflight($this->types)->prepare($draft);
        } finally {
            static::assertSame($json, $draft->json);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidGraphProvider(): iterable
    {
        yield 'incomplete configuration is writable but cannot be frozen' => ['"outcome": "approved"', ''];
        yield 'duplicate keys cannot be hidden by canonical encoding' => ['"schemaVersion":', '"id":"hidden", "schemaVersion":'];
        yield 'unknown declaration cannot become a dependency' => ['core:approval', 'finance:missing'];
        yield 'another version cannot replace the requested version' => ['"typeVersion": "1.0.0"', '"typeVersion": "2.0.0"'];
        yield 'dangling connection is never repaired' => ['"target": "review"', '"target": "gone"'];
        yield 'arbitrary cycle prevents a complete graph' => ['"target": "end"', '"target": "review"'];
        yield 'unregistered extension cannot become an untyped dependency' => ['"extensions": {}', '"extensions": {"finance:settings":{}}'];
        yield 'invalid present configuration is never defaulted' => ['"emptyAssignees": "incident"', '"emptyAssignees": null'];
    }

    public function testWhitespaceAndRegistryOrderDoNotChangeTheSnapshot(): void
    {
        $first = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));
        $second = $this->preflight(array_reverse($this->types))->prepare(new DraftGraph(str_replace('    ', '', GraphFixture::json())));

        static::assertSame($first->graphJson, $second->graphJson);
        static::assertSame($first->contractJson, $second->contractJson);
        static::assertSame($first->hash, $second->hash);
    }

    #[DataProvider('authoredChangeProvider')]
    public function testAuthoredChangesAffectBothHashesWhileTheContractStaysFixed(string $search, string $replacement): void
    {
        $preflight = $this->preflight($this->types);
        $first = $preflight->prepare(new DraftGraph(GraphFixture::json()));

        $second = $preflight->prepare(new DraftGraph(str_replace($search, $replacement, GraphFixture::json())));

        static::assertSame($first->contractJson, $second->contractJson);
        static::assertNotSame($first->graphHash, $second->graphHash);
        static::assertNotSame($first->hash, $second->hash);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function authoredChangeProvider(): iterable
    {
        yield 'layout remains part of the full graph hash' => ['"width": 180', '"width": 181'];
        yield 'approval configuration remains authored input' => ['core:all', 'core:any'];
        yield 'metadata is included in the graph snapshot' => ['Transfer approval', 'Updated transfer approval'];
    }

    public function testUnusedDeclarationsAndDeploymentMetadataDoNotChangeTheSnapshot(): void
    {
        $first = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));
        $type = $this->types['core:approval@1.0.0'];
        $this->types['core:approval@1.0.0'] = new NodeTypeSpecification(
            $type->type,
            $type->version,
            'renamed.label',
            '/another/deployment/approval.yaml',
            $type->configurationSchema,
            array_reverse($type->ports),
            $type->draftConfigurationSchema . "\n",
        );
        $this->types['finance:unused@1.0.0'] = new NodeTypeSpecification(
            'finance:unused',
            '1.0.0',
            'finance.unused',
            'plugin:finance',
            '{"$id":"urn:finance:unused:1.0.0","type":"object"}',
            ports: [],
        );

        $second = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));

        static::assertSame($first->contractJson, $second->contractJson);
        static::assertSame($first->hash, $second->hash);
    }

    public function testSchemaBytesAreFrozenEvenWhenTheirVersionAndMeaningAreUnchanged(): void
    {
        $first = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));
        $type = $this->types['core:approval@1.0.0'];
        $this->types['core:approval@1.0.0'] = new NodeTypeSpecification(
            $type->type,
            $type->version,
            $type->labelSnippet,
            $type->source,
            $type->configurationSchema . "\n",
            $type->ports,
            $type->draftConfigurationSchema,
        );

        $second = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));

        static::assertSame($first->graphHash, $second->graphHash);
        static::assertNotSame($first->hash, $second->hash);
        $contract = json_decode($first->contractJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($contract);
        static::assertIsArray($contract['nodeTypes']);
        static::assertIsArray($contract['nodeTypes'][0]);
        static::assertSame($type->configurationSchema, $contract['nodeTypes'][0]['configurationSchema']);
        static::assertSame($type->configurationSchemaHash, $contract['nodeTypes'][0]['configurationSchemaHash']);
    }

    public function testTheGraphSchemaBytesArePartOfTheFrozenContract(): void
    {
        $first = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));
        $this->graphSchema .= "\n";

        $second = $this->preflight($this->types)->prepare(new DraftGraph(GraphFixture::json()));

        static::assertSame($first->graphJson, $second->graphJson);
        static::assertNotSame($first->hash, $second->hash);
    }

    public function testBusinessNodeDeclarationsAreCapturedThroughTheExistingLoaderTag(): void
    {
        $filesystem = new Filesystem();
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection'));
        $loader->load('approval-flow.php');
        $container->register('finance.approval.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', __DIR__ . '/../Graph/Type/Registry/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');
        $container->getDefinition(GraphPublicationPreflight::class)->setPublic(true);
        $container->compile();
        $preflight = $container->get(GraphPublicationPreflight::class);
        static::assertInstanceOf(GraphPublicationPreflight::class, $preflight);
        $json = $filesystem->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        $snapshot = $preflight->prepare(new DraftGraph($json));

        $contract = json_decode($snapshot->contractJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($contract);
        static::assertIsArray($contract['nodeTypes']);
        static::assertSame(['core:end', 'core:start', 'finance:review'], array_column($contract['nodeTypes'], 'type'));
        static::assertIsArray($contract['nodeTypes'][2]);
        static::assertSame('1.0.0', $contract['nodeTypes'][2]['version']);
        static::assertSame($filesystem->readFile(__DIR__ . '/../Graph/Type/Registry/_fixtures/review.json'), $contract['nodeTypes'][2]['configurationSchema']);
        foreach (['"limit":0', '"enabled":false', '"note":null', '"options":{}', '"groups":[]'] as $fragment) {
            static::assertStringContainsString($fragment, $snapshot->graphJson);
        }
    }

    public function testRepeatedNodeTypesAreFrozenOnce(): void
    {
        $payload = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $payload);
        static::assertIsArray($payload->nodes);
        static::assertInstanceOf(\stdClass::class, $payload->nodes[1]);
        $review = clone $payload->nodes[1];
        $review->id = 'second-review';
        $payload->nodes[] = $review;
        static::assertIsArray($payload->connections);
        static::assertInstanceOf(\stdClass::class, $payload->connections[1]);
        $toEnd = clone $payload->connections[1];
        $toEnd->id = 'second-review-end';
        $toEnd->source = 'second-review';
        $payload->connections[1]->target = 'second-review';
        $payload->connections[] = $toEnd;

        $snapshot = $this->preflight($this->types)->prepare(new DraftGraph(json_encode($payload, \JSON_THROW_ON_ERROR)));

        $contract = json_decode($snapshot->contractJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($contract);
        static::assertSame(['core:approval', 'core:end', 'core:start'], array_column($contract['nodeTypes'], 'type'));
        static::assertStringContainsString('"id":"second-review"', $snapshot->graphJson);
    }

    /**
     * @param array<string, NodeTypeSpecification> $types
     */
    private function preflight(array $types): GraphPublicationPreflight
    {
        $registry = new NodeTypeRegistry([new PublicationNodeTypeLoader(array_values($types))]);
        $decoder = new GraphJsonDecoder();
        $diagnostics = new GraphDiagnostics($registry, new GraphSchemaValidator(), $this->graphSchema, $decoder);

        return new GraphPublicationPreflight(new GraphCodec($diagnostics, $decoder), $diagnostics, $registry, $decoder, $this->graphSchema);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class PublicationNodeTypeLoader implements NodeTypeLoader
{
    /**
     * @param list<NodeTypeSpecification> $types
     */
    public function __construct(private readonly array $types)
    {
    }

    public function load(): array
    {
        return $this->types;
    }
}
