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
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\CoreNodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodePlanCompiler::class)]
class NodePlanCompilerTest extends TestCase
{
    public function testCompilesTheCorePathThroughRealServiceAssembly(): void
    {
        $container = $this->container();
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);
        $draft = new DraftGraph(GraphFixture::json());

        $compiled = $compiler->compile($draft);

        $plan = json_decode($compiled->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertSame(NodePlanCompiler::ID, $plan['compilerId']);
        static::assertSame($compiled->graphPlan->hash, $plan['graphPlanHash']);
        static::assertSame([
            ['nodeId' => 'start', 'type' => 'core:start', 'typeVersion' => '1.0.0', 'configuration' => ['requestType' => 'payment:transfer']],
            ['nodeId' => 'review', 'type' => 'core:approval', 'typeVersion' => '1.0.0', 'configuration' => ['userIds' => ['finance-user'], 'policy' => 'core:all', 'reject' => 'veto', 'fields' => [], 'emptyAssignees' => 'incident']],
            ['nodeId' => 'end', 'type' => 'core:end', 'typeVersion' => '1.0.0', 'configuration' => ['outcome' => 'approved']],
        ], $plan['nodes']);
        static::assertSame(GraphFixture::json(), $draft->json);
    }

    public function testRemovingTheCompilerDoesNotLeaveASchemaOnlySuccessPath(): void
    {
        $container = $this->container();
        $container->removeDefinition(CoreNodeCompiler::class);
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);
        $this->expectExceptionObject(ApprovalFlowException::nodeCompilerNotFound('core:start', '1.0.0'));

        $compiler->compile(new DraftGraph(GraphFixture::json()));
    }

    public function testSemanticFailureIdentifiesTheNodeConfigurationPath(): void
    {
        $container = $this->container();
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);
        $draft = new DraftGraph(str_replace('"finance-user"', '"one", "two"', str_replace('core:all', 'core:single', GraphFixture::json())));

        try {
            $compiler->compile($draft);
            static::fail('Single approval with two users must fail semantic compilation.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_CONFIGURATION, $exception->getErrorCode());
            static::assertSame('/nodes/1/config/policy/kind', $exception->getParameters()['path']);
        }
        static::assertStringContainsString('"one", "two"', $draft->json);
    }

    public function testEquivalentFormattingProducesIdenticalFrozenPlans(): void
    {
        $container = $this->container();
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);

        $first = $compiler->compile(new DraftGraph(GraphFixture::json()));
        $second = $compiler->compile(new DraftGraph(str_replace('    ', '', GraphFixture::json())));

        static::assertSame($first->json, $second->json);
        static::assertSame($first->hash, $second->hash);
    }

    public function testBusinessCompilerIsDiscoveredWithoutAFrameworkBranch(): void
    {
        $container = $this->financeContainer();
        $container->register(FinanceNodeCompiler::class)->addTag('approval_flow.node_compiler');
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        $compiled = $compiler->compile(new DraftGraph($draft));

        $plan = json_decode($compiled->json, flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $plan);
        static::assertIsArray($plan->nodes);
        static::assertInstanceOf(\stdClass::class, $plan->nodes[1]);
        $configuration = $plan->nodes[1]->configuration;
        static::assertInstanceOf(\stdClass::class, $configuration);
        static::assertSame(0, $configuration->limit);
        static::assertFalse($configuration->enabled);
        static::assertNull($configuration->note);
        static::assertInstanceOf(\stdClass::class, $configuration->options);
        static::assertSame([], $configuration->groups);
    }

    public function testADeclarationWithoutItsBusinessCompilerCannotProduceANodePlan(): void
    {
        $container = $this->financeContainer();
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');
        $this->expectExceptionObject(ApprovalFlowException::nodeCompilerNotFound('finance:review', '1.0.0'));

        $compiler->compile(new DraftGraph($draft));
    }

    public function testReusedMutablePluginResultsCannotRewriteEarlierNodesOrPlans(): void
    {
        $container = $this->container();
        $container->compile();
        $graphCompiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $graphCompiler);
        $configuration = new MutableNodeConfiguration();
        $nodeCompiler = static::createStub(NodeCompiler::class);
        $nodeCompiler->method('getTypes')->willReturn(['core:start' => ['1.0.0'], 'core:approval' => ['1.0.0'], 'core:end' => ['1.0.0']]);
        $nodeCompiler->method('compile')->willReturnCallback(static function (string $type) use ($configuration): NodeConfiguration {
            $configuration->value = $type;

            return $configuration;
        });
        $compiler = new NodePlanCompiler($graphCompiler, new NodeCompilerRegistry([$nodeCompiler]), new GraphJsonDecoder());

        $first = $compiler->compile(new DraftGraph(GraphFixture::json()));
        $firstJson = $first->json;
        $compiler->compile(new DraftGraph(GraphFixture::json()));
        $configuration->value = 'changed';

        static::assertSame($firstJson, $first->json);
        $plan = json_decode($first->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertIsArray($plan['nodes']);
        static::assertSame(['core:start', 'core:approval', 'core:end'], array_column(array_column($plan['nodes'], 'configuration'), 'value'));
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('invalidCompilerOutputProvider')]
    public function testInvalidCompilerOutputIsAProducerFault(array $data): void
    {
        $container = $this->container();
        $container->compile();
        $graphCompiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $graphCompiler);
        $configuration = static::createStub(NodeConfiguration::class);
        $configuration->method('jsonSerialize')->willReturn($data);
        $nodeCompiler = static::createStub(NodeCompiler::class);
        $nodeCompiler->method('getTypes')->willReturn(['core:start' => ['1.0.0']]);
        $nodeCompiler->method('compile')->willReturn($configuration);
        $compiler = new NodePlanCompiler($graphCompiler, new NodeCompilerRegistry([$nodeCompiler]), new GraphJsonDecoder());

        try {
            $compiler->compile(new DraftGraph(GraphFixture::json()));
            static::fail('Invalid compiler output must not become a frozen plan.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_COMPILER, $exception->getErrorCode());
            static::assertSame(500, $exception->getStatusCode());
        }
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidCompilerOutputProvider(): iterable
    {
        yield 'list instead of object' => [[1, 2]];
        yield 'non-finite number' => [['value' => \NAN]];
        yield 'unsafe integer' => [['value' => 9007199254740992]];
        yield 'unrepresentable object key' => [["\0field" => 'value']];
    }

    public function testNodePlanMatchesItsVersionedSchemaAndPreservesNumericIds(): void
    {
        $container = $this->container();
        $container->compile();
        $compiler = $container->get(NodePlanCompiler::class);
        static::assertInstanceOf(NodePlanCompiler::class, $compiler);
        $json = str_replace(['"start"', '"review"', '"end"'], ['"0"', '"01"', '"2"'], GraphFixture::json());

        $compiled = $compiler->compile(new DraftGraph($json));

        $plan = json_decode($compiled->json, flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $plan);
        static::assertIsArray($plan->nodes);
        static::assertSame(['0', '01', '2'], array_column($plan->nodes, 'nodeId'));
        $schema = (new Filesystem())->readFile(\dirname(__DIR__, 6) . '/src/Core/Framework/ApprovalFlow/Resources/schema/plan/1.0.0/node-plan.schema.json');
        static::assertSame([], (new GraphSchemaValidator())->validate($plan, $schema));
    }

    public function testSerializerValidationExceptionIsAProducerFaultRatherThanInvalidInput(): void
    {
        $container = $this->container();
        $container->compile();
        $graphCompiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $graphCompiler);
        $failure = new class(Response::HTTP_UNPROCESSABLE_ENTITY, 'FINANCE__ENCODE_ERROR', 'Configuration serialization failed.') extends HttpException {};
        $configuration = static::createStub(NodeConfiguration::class);
        $configuration->method('jsonSerialize')->willThrowException($failure);
        $nodeCompiler = static::createStub(NodeCompiler::class);
        $nodeCompiler->method('getTypes')->willReturn(['core:start' => ['1.0.0']]);
        $nodeCompiler->method('compile')->willReturn($configuration);
        $compiler = new NodePlanCompiler($graphCompiler, new NodeCompilerRegistry([$nodeCompiler]), new GraphJsonDecoder());

        try {
            $compiler->compile(new DraftGraph(GraphFixture::json()));
            static::fail('A serialization failure must abort compilation as a producer fault.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_COMPILER, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testBusinessValidationFailureGetsNodeContextAndRetainsItsDomainCause(): void
    {
        $failure = new class(Response::HTTP_UNPROCESSABLE_ENTITY, 'FINANCE__INVALID_RULE', 'Review limit is invalid.', ['path' => '/limit']) extends HttpException {};
        $compiler = $this->compilerWithFinanceFailure($failure);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        try {
            $compiler->compile(new DraftGraph($draft));
            static::fail('The business validation failure must abort compilation.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_CONFIGURATION, $exception->getErrorCode());
            static::assertSame('/nodes/1/config/limit', $exception->getParameters()['path']);
            static::assertSame('finance:review', $exception->getParameters()['type']);
            static::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testBusinessValidationWithoutALocalPathIdentifiesTheWholeConfiguration(): void
    {
        $failure = new class(Response::HTTP_BAD_REQUEST, 'FINANCE__INVALID_RULE', 'Review settings are invalid.') extends HttpException {};
        $compiler = $this->compilerWithFinanceFailure($failure);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        try {
            $compiler->compile(new DraftGraph($draft));
            static::fail('The business validation failure must abort compilation.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame('/nodes/1/config', $exception->getParameters()['path']);
            static::assertSame($failure, $exception->getPrevious());
        }
    }

    #[DataProvider('operationalStatusProvider')]
    public function testBusinessOperationalFailuresPropagateUnchanged(int $status): void
    {
        $failure = new class($status, 'FINANCE__UNAVAILABLE', 'Finance compiler is unavailable.') extends HttpException {};
        $compiler = $this->compilerWithFinanceFailure($failure);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        try {
            $compiler->compile(new DraftGraph($draft));
            static::fail('Operational failures must propagate without reclassification.');
        } catch (HttpException $exception) {
            static::assertSame($failure, $exception);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function operationalStatusProvider(): iterable
    {
        yield 'operational server failure' => [Response::HTTP_INTERNAL_SERVER_ERROR];
        yield 'permission failure is not invalid input' => [Response::HTTP_FORBIDDEN];
    }

    public function testMalformedBusinessErrorPathIsACompilerFault(): void
    {
        $failure = new class(Response::HTTP_UNPROCESSABLE_ENTITY, 'FINANCE__INVALID_RULE', 'Invalid limit.', ['path' => 'limit']) extends HttpException {};
        $compiler = $this->compilerWithFinanceFailure($failure);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        try {
            $compiler->compile(new DraftGraph($draft));
            static::fail('A present malformed error path must not be silently replaced.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_COMPILER, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testUnexpectedBusinessCompilerFailureIsNotConvertedIntoValidation(): void
    {
        $failure = new \RuntimeException('Compiler implementation failed.');
        $compiler = $this->compilerWithFinanceFailure($failure);
        $draft = (new Filesystem())->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');
        $this->expectExceptionObject($failure);

        $compiler->compile(new DraftGraph($draft));
    }

    private function compilerWithFinanceFailure(\Throwable $failure): NodePlanCompiler
    {
        $container = $this->financeContainer();
        $container->getDefinition(NodeCompilerRegistry::class)->setPublic(true);
        $container->compile();
        $graphCompiler = $container->get(GraphPlanCompiler::class);
        static::assertInstanceOf(GraphPlanCompiler::class, $graphCompiler);
        $coreCompilers = $container->get(NodeCompilerRegistry::class);
        static::assertInstanceOf(NodeCompilerRegistry::class, $coreCompilers);
        $financeCompiler = static::createStub(NodeCompiler::class);
        $financeCompiler->method('getTypes')->willReturn(['finance:review' => ['1.0.0']]);
        $financeCompiler->method('compile')->willThrowException($failure);

        return new NodePlanCompiler($graphCompiler, new NodeCompilerRegistry([$coreCompilers->get('core:start', '1.0.0'), $financeCompiler]), new GraphJsonDecoder());
    }

    private function financeContainer(): ContainerBuilder
    {
        $container = $this->container();
        $container->register('finance.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', __DIR__ . '/../Graph/Type/Registry/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');

        return $container;
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection'));
        $loader->load('approval-flow.php');
        $container->getDefinition(NodePlanCompiler::class)->setPublic(true);
        $container->getDefinition(GraphPlanCompiler::class)->setPublic(true);

        return $container;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class FinanceNodeCompiler implements NodeCompiler
{
    public function getTypes(): array
    {
        return ['finance:review' => ['1.0.0']];
    }

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        $data = json_decode($configurationJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data) || !\is_int($data['limit'] ?? null) || !\is_bool($data['enabled'] ?? null)) {
            throw new \RuntimeException('Invalid finance fixture configuration.');
        }

        return new FinanceNodeConfiguration($data['limit'], $data['enabled']);
    }
}

/**
 * @internal
 */
#[Package('framework')]
final readonly class FinanceNodeConfiguration implements NodeConfiguration
{
    public function __construct(public int $limit, public bool $enabled)
    {
    }

    public function jsonSerialize(): array
    {
        return ['limit' => $this->limit, 'enabled' => $this->enabled, 'note' => null, 'options' => new \stdClass(), 'groups' => []];
    }
}

/**
 * @internal
 */
#[Package('framework')]
class MutableNodeConfiguration implements NodeConfiguration
{
    public string $value = '';

    public function jsonSerialize(): array
    {
        return ['value' => $this->value];
    }
}
