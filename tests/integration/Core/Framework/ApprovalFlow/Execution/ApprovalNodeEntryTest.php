<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\ApprovalExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\NodeExecutionContext;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\ApprovalFlow\Release\PublishedApprovalRelease;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeCompiler;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeConfiguration;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeExecutor;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalNodeEntryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-release-entry-' . Uuid::randomHex();
        $this->container = $this->runtimeContainer();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testPersistedCoreConfigurationsEnterTheirExactExecutorsWithoutChangingStorage(): void
    {
        $release = $this->publish(GraphFixture::json());
        $entry = $this->container->get(ApprovalNodeEntry::class);
        $context = Context::createDefaultContext();
        $connection = static::getContainer()->get(Connection::class);
        $before = $connection->fetchAssociative('SELECT * FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($release->id)]);

        $start = $entry->enter($release->id, 'start');
        $review = $entry->enter($release->id, 'review');
        $end = $entry->enter($release->id, 'end');

        static::assertInstanceOf(AdvanceEffect::class, $start);
        static::assertInstanceOf(RequestApprovalEffect::class, $review);
        static::assertSame(['finance-user'], $review->configuration->userIds);
        static::assertSame('core:all', $review->configuration->policy);
        static::assertInstanceOf(CompleteEffect::class, $end);
        static::assertSame(ApprovalOutcome::Approved, $end->outcome);
        static::assertSame($before, $connection->fetchAssociative('SELECT * FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($release->id)]));
        static::assertSame(1, static::getContainer()->get(ApprovalDraftService::class)->read($release->definitionId, $context)->revision);
    }

    public function testBusinessConfigurationIsRestoredThroughItsTaggedCompilerAndExecutor(): void
    {
        $this->registerFinance(FinanceNodeCompiler::class);
        $graph = $this->filesystem->readFile(\dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph/_fixtures/finance-transfer.json');
        $release = $this->publish($graph);

        $effect = $this->container->get(ApprovalNodeEntry::class)->enter($release->id, 'review');

        static::assertInstanceOf(CompleteEffect::class, $effect);
        static::assertSame(ApprovalOutcome::Rejected, $effect->outcome);
        $executor = $this->container->get(RecordingFinanceExecutor::class);
        static::assertInstanceOf(RecordingFinanceExecutor::class, $executor);
        static::assertSame(1, $executor->calls);
    }

    public function testSerialProgressionCannotCompleteAtABusinessNode(): void
    {
        $this->registerFinance(FinanceNodeCompiler::class);
        $graph = $this->filesystem->readFile(\dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph/_fixtures/finance-transfer.json');
        $release = $this->publish($graph);
        $this->expectExceptionObject(ApprovalFlowException::invalidGraphPlan('Only the published end boundary may complete the process.'));

        $this->container->get(ApprovalNodeEntry::class)->enterSuccessors($release->id, 'start');
    }

    public function testCorruptUnrelatedContentPreventsTheSelectedExecutorFromEntering(): void
    {
        $this->registerFinance(FinanceNodeCompiler::class);
        $graph = $this->filesystem->readFile(\dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph/_fixtures/finance-transfer.json');
        $release = $this->publish($graph);
        static::getContainer()->get(Connection::class)->update('approval_flow_release', ['contract_json' => '{}'], ['id' => Uuid::fromHexToBytes($release->id)]);

        try {
            $this->container->get(ApprovalNodeEntry::class)->enter($release->id, 'review');
            static::fail('The entire release must be verified before entering a selected node.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::RELEASE_VERIFICATION_FAILED, $exception->getErrorCode());
            static::assertSame('contractJson', $exception->getParameters()['part']);
        }
        $executor = $this->container->get(RecordingFinanceExecutor::class);
        static::assertInstanceOf(RecordingFinanceExecutor::class, $executor);
        static::assertSame(0, $executor->calls);
    }

    public function testACompilerChangingAfterReleaseVerificationCannotEnterItsExecutor(): void
    {
        $this->registerFinance(DriftingFinanceCompiler::class);
        $graph = $this->filesystem->readFile(\dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph/_fixtures/finance-transfer.json');
        $release = $this->publish($graph);

        try {
            $this->container->get(ApprovalNodeEntry::class)->enter($release->id, 'review');
            static::fail('The actual typed configuration must match the frozen node plan.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::RELEASE_VERIFICATION_FAILED, $exception->getErrorCode());
            static::assertSame('nodePlanJson/nodes/1/configuration', $exception->getParameters()['part']);
        }
        $executor = $this->container->get(RecordingFinanceExecutor::class);
        static::assertInstanceOf(RecordingFinanceExecutor::class, $executor);
        static::assertSame(0, $executor->calls);
    }

    public function testAnUnboundExecutorArtifactPreventsAnyNodeEntry(): void
    {
        $this->registerFinance(FinanceNodeCompiler::class);
        $graph = $this->filesystem->readFile(\dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph/_fixtures/finance-transfer.json');
        $release = $this->publish($graph);
        $path = $this->directory . '/manifest.json';
        $manifest = json_decode($this->filesystem->readFile($path), associative: true, flags: \JSON_THROW_ON_ERROR);
        $manifest['services'] = array_values(array_filter($manifest['services'], static fn (array $binding): bool => $binding['class'] !== EndExecutor::class));
        $this->filesystem->dumpFile($path, json_encode($manifest, \JSON_THROW_ON_ERROR));

        try {
            $this->container->get(ApprovalNodeEntry::class)->enter($release->id, 'review');
            static::fail('Every used executor artifact must be bound before any node enters.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::ARTIFACT_SERVICE_NOT_FOUND, $exception->getErrorCode());
        }
        $executor = $this->container->get(RecordingFinanceExecutor::class);
        static::assertInstanceOf(RecordingFinanceExecutor::class, $executor);
        static::assertSame(0, $executor->calls);
    }

    public function testAMissingExactExecutorCannotUseAnotherInstalledVersion(): void
    {
        $release = $this->publish(GraphFixture::json());
        $runtime = $this->runtimeContainer();
        $runtime->removeDefinition(EndExecutor::class);
        $runtime->register(NextEndExecutor::class)->addTag('approval_flow.node_executor');
        $runtime->compile();
        $runtime->set(ApprovalReleaseStore::class, static::getContainer()->get(ApprovalReleaseStore::class));
        $this->expectExceptionObject(ApprovalFlowException::nodeExecutorNotFound('core:end', '1.0.0'));

        $runtime->get(ApprovalNodeEntry::class)->enter($release->id, 'end');
    }

    public function testUnknownNodeDoesNotUseAnotherNodesConfiguration(): void
    {
        $release = $this->publish(GraphFixture::json());
        $this->expectExceptionObject(ApprovalFlowException::releaseNodeNotFound($release->id, 'missing'));

        $this->container->get(ApprovalNodeEntry::class)->enter($release->id, 'missing');
    }

    public function testApprovalExecutorCannotSkipApprovalByReturningCompletion(): void
    {
        $this->replaceExecutor(CoreNodeType::APPROVAL, ApprovalExecutor::class);
        $release = $this->publish(GraphFixture::json());
        $this->assertInvalidEffect($release, 'review', 'Effect is not allowed by the published node declaration.');
    }

    public function testApprovalExecutorCannotReplaceFrozenCandidates(): void
    {
        $this->replaceExecutor(CoreNodeType::APPROVAL, ApprovalExecutor::class);
        $release = $this->publish(GraphFixture::json());
        $executor = $this->container->get(ChangedNodeExecutor::class);
        static::assertInstanceOf(ChangedNodeExecutor::class, $executor);
        $executor->effect = new RequestApprovalEffect(new ApprovalNodeConfiguration(
            ['another-user'],
            ApprovalNodeConfiguration::ALL,
            [],
            ApprovalNodeConfiguration::EMPTY_INCIDENT,
        ));
        $this->assertInvalidEffect($release, 'review', 'Approval rules differ from the compiled node configuration.');
    }

    public function testEndExecutorCannotReplaceTheFrozenOutcome(): void
    {
        $this->replaceExecutor(CoreNodeType::END, EndExecutor::class);
        $release = $this->publish(GraphFixture::json());
        $executor = $this->container->get(ChangedNodeExecutor::class);
        static::assertInstanceOf(ChangedNodeExecutor::class, $executor);
        $executor->effect = new CompleteEffect(ApprovalOutcome::Rejected);
        $this->assertInvalidEffect($release, 'end', 'Completion outcome differs from the compiled node configuration.');
    }

    public function testChangedEffectPermissionsCannotBeUsedWithAnExistingRelease(): void
    {
        $release = $this->publish(GraphFixture::json());
        $runtime = $this->runtimeContainer();
        $runtime->getDefinition(YamlNodeTypeLoader::class)->setClass(ChangedEffectPermissionsLoader::class);
        $runtime->compile();
        $runtime->set(ApprovalReleaseStore::class, static::getContainer()->get(ApprovalReleaseStore::class));
        $this->expectExceptionObject(ApprovalFlowException::releaseVerificationFailed($release->id, 'contractJson'));

        $runtime->get(ApprovalNodeEntry::class)->enter($release->id, 'review');
    }

    private function assertInvalidEffect(PublishedApprovalRelease $release, string $nodeId, string $reason): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $parameters = ['id' => Uuid::fromHexToBytes($release->id)];
        $before = $connection->fetchAssociative('SELECT * FROM approval_flow_release WHERE id = :id', $parameters);
        try {
            $this->container->get(ApprovalNodeEntry::class)->enter($release->id, $nodeId);
            static::fail('Invalid executor output must not escape the verified entry boundary.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_EFFECT, $exception->getErrorCode());
            static::assertSame($reason, $exception->getParameters()['reason']);
            static::assertSame('1.0.0', $exception->getParameters()['version']);
        }
        static::assertSame($before, $connection->fetchAssociative('SELECT * FROM approval_flow_release WHERE id = :id', $parameters));
        static::assertSame(1, static::getContainer()->get(ApprovalDraftService::class)->read($release->definitionId, Context::createDefaultContext())->revision);
    }

    private function replaceExecutor(string $type, string $executor): void
    {
        $this->container->removeDefinition($executor);
        $this->container->register(ChangedNodeExecutor::class)->setArguments([$type])->addTag('approval_flow.node_executor')->setPublic(true);
        PublicationFixture::writeManifest($this->filesystem, $this->directory, [ChangedNodeExecutor::class]);
    }

    private function runtimeContainer(): ContainerBuilder
    {
        $path = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $container = PublicationFixture::container($path);
        $container->register(ApprovalReleaseStore::class)->setSynthetic(true)->setPublic(true);
        $container->register(ApprovalDraftStore::class)->setSynthetic(true)->setPublic(true);
        $container->getDefinition(ApprovalDraftService::class)->setPublic(true);
        $container->getDefinition(ApprovalReleaseService::class)->setPublic(true);
        $container->getDefinition(ApprovalNodeEntry::class)->setPublic(true);

        return $container;
    }

    private function publish(string $json): PublishedApprovalRelease
    {
        $this->container->compile();
        $this->container->set(ApprovalReleaseStore::class, static::getContainer()->get(ApprovalReleaseStore::class));
        $this->container->set(ApprovalDraftStore::class, static::getContainer()->get(ApprovalDraftStore::class));
        $context = Context::createDefaultContext();
        $draft = $this->container->get(ApprovalDraftService::class)->create('Transfer approval', $json, $context);
        $compiled = $this->container->get(ApprovalReleaseCompiler::class)->compile(new DraftGraph($json));

        return $this->container->get(ApprovalReleaseService::class)->publish($draft->definitionId, 1, $compiled->hash, $context);
    }

    /**
     * @param class-string<FinanceNodeCompiler> $compiler
     */
    private function registerFinance(string $compiler): void
    {
        $this->container->register('finance.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', \dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph/Type/Registry/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');
        $this->container->register($compiler)->addTag('approval_flow.node_compiler');
        $this->container->register(RecordingFinanceExecutor::class)->addTag('approval_flow.node_executor')->setPublic(true);
        PublicationFixture::writeManifest($this->filesystem, $this->directory, [$compiler, RecordingFinanceExecutor::class]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class RecordingFinanceExecutor extends FinanceNodeExecutor
{
    public int $calls = 0;

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): CompleteEffect
    {
        ++$this->calls;

        return parent::enter($configuration);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class DriftingFinanceCompiler extends FinanceNodeCompiler
{
    private int $calls = 0;

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        ++$this->calls;

        // Preview and locked publication agree; loading also agrees, then node restoration drifts.
        return new FinanceNodeConfiguration($this->calls >= 4 ? 1 : 0, false);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class NextEndExecutor extends EndExecutor
{
    public function getVersion(): string
    {
        return '2.0.0';
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ChangedNodeExecutor implements NodeExecutor
{
    public AdvanceEffect|RequestApprovalEffect|CompleteEffect $effect;

    public function __construct(private readonly string $type)
    {
        $this->effect = new CompleteEffect(ApprovalOutcome::Approved);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect|RequestApprovalEffect|CompleteEffect
    {
        return $this->effect;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ChangedEffectPermissionsLoader extends YamlNodeTypeLoader
{
    public function load(): array
    {
        $types = [];
        foreach (parent::load() as $type) {
            $types[] = new NodeTypeSpecification(
                $type->type,
                $type->version,
                $type->labelSnippet,
                $type->source,
                $type->configurationSchema,
                $type->ports,
                [NodeEffectType::Advance, NodeEffectType::RequestApproval, NodeEffectType::Complete],
                $type->draftConfigurationSchema,
            );
        }

        return $types;
    }
}
