<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReleaseLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ApprovalReleaseService $releases;

    private ApprovalReleaseCompiler $compiler;

    private ApprovalReleaseLoader $loader;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-release-load-' . Uuid::randomHex();
        $path = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $container = PublicationFixture::container($path);
        $container->register(ApprovalReleaseStore::class)->setSynthetic(true)->setPublic(true);
        $container->getDefinition(ApprovalReleaseService::class)->setPublic(true);
        $container->getDefinition(ApprovalReleaseLoader::class)->setPublic(true);
        $container->compile();
        $container->set(ApprovalReleaseStore::class, static::getContainer()->get(ApprovalReleaseStore::class));
        $this->releases = $container->get(ApprovalReleaseService::class);
        $this->compiler = $container->get(ApprovalReleaseCompiler::class);
        $this->loader = $container->get(ApprovalReleaseLoader::class);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testLoadsThePublishedGraphAfterLaterDraftEditsWithoutChangingStorage(): void
    {
        $context = Context::createDefaultContext();
        $drafts = static::getContainer()->get(ApprovalDraftService::class);
        $draft = $drafts->create('Transfer approval', GraphFixture::json(), $context);
        $compiled = $this->compiler->compile($draft->graph);
        $release = $this->releases->publish($draft->definitionId, 1, $compiled->hash, $context);
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph);
        static::assertIsArray($graph->nodes);
        $node = $graph->nodes[1];
        static::assertInstanceOf(\stdClass::class, $node);
        $node->id = 'later-review';
        $drafts->addNode($draft->definitionId, 1, json_encode($node, \JSON_THROW_ON_ERROR), $context);
        $connection = static::getContainer()->get(Connection::class);
        $before = $connection->fetchAssociative('SELECT * FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($release->id)]);

        $loaded = $this->loader->load($release->id, $context);

        static::assertSame($compiled->hash, $loaded->hash);
        static::assertSame($compiled->graphJson, $loaded->graphJson);
        static::assertSame($before, $connection->fetchAssociative('SELECT * FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($release->id)]));
        static::assertSame(2, $drafts->read($draft->definitionId, $context)->revision);
    }

    #[DataProvider('changedPartProvider')]
    public function testRejectsEachChangedFrozenPart(string $column, string $part, string $value): void
    {
        $context = Context::createDefaultContext();
        $draft = static::getContainer()->get(ApprovalDraftService::class)->create('Transfer approval', GraphFixture::json(), $context);
        $compiled = $this->compiler->compile($draft->graph);
        $release = $this->releases->publish($draft->definitionId, 1, $compiled->hash, $context);
        static::getContainer()->get(Connection::class)->update('approval_flow_release', [$column => $value], ['id' => Uuid::fromHexToBytes($release->id)]);
        $this->expectExceptionObject(ApprovalFlowException::releaseVerificationFailed($release->id, $part));

        $this->loader->load($release->id, $context);
    }

    public static function changedPartProvider(): \Generator
    {
        yield 'graph bytes remain exact' => ['graph_json', 'graphJson', GraphFixture::json()];
        yield 'declaration contract is frozen' => ['contract_json', 'contractJson', '{}'];
        yield 'graph plan is frozen' => ['graph_plan_json', 'graphPlanJson', '{}'];
        yield 'typed node plan is frozen' => ['node_plan_json', 'nodePlanJson', '{}'];
        yield 'artifact closure is frozen' => ['dependencies_json', 'dependenciesJson', '{}'];
        yield 'full content hash is verified' => ['content_hash', 'contentHash', str_repeat('b', 64)];
    }

    public function testChangedDeploymentCannotLoadAnOlderReleaseAsCurrentCode(): void
    {
        $context = Context::createDefaultContext();
        $draft = static::getContainer()->get(ApprovalDraftService::class)->create('Transfer approval', GraphFixture::json(), $context);
        $compiled = $this->compiler->compile($draft->graph);
        $release = $this->releases->publish($draft->definitionId, 1, $compiled->hash, $context);
        $manifest = json_decode($this->filesystem->readFile($this->directory . '/manifest.json'), associative: true, flags: \JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($this->directory . '/core.artifact', 'Changed synthetic deployment.');
        $manifest['artifacts'][0]['sha256'] = hash('sha256', 'Changed synthetic deployment.');
        $this->filesystem->dumpFile($this->directory . '/manifest.json', json_encode($manifest, \JSON_THROW_ON_ERROR));
        $this->expectExceptionObject(ApprovalFlowException::releaseVerificationFailed($release->id, 'dependenciesJson'));

        $this->loader->load($release->id, $context);
    }

    public function testReadPrivilegesAreCheckedBeforeLookupOrArtifactAccess(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $this->filesystem->remove($this->directory . '/manifest.json');
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_release:read']));

        $this->loader->load(Uuid::randomHex(), $context);
    }

    public function testMissingReleaseDoesNotBecomeAnEmptyPlan(): void
    {
        $id = Uuid::randomHex();
        $this->expectExceptionObject(ApprovalFlowException::releaseNotFound($id));

        $this->loader->load($id, Context::createDefaultContext());
    }
}
