<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Release;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraft;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseWriteGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReleaseServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ApprovalDraftService $drafts;

    private ApprovalReleaseCompiler $compiler;

    private ApprovalReleaseService $publisher;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-release-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
        $container = static::getContainer();
        $path = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $this->compiler = PublicationFixture::releaseCompiler(
            $container->get(NodePlanCompiler::class),
            $container->get(NodeCompilerRegistry::class),
            $container->get(NodeExecutorRegistry::class),
            $this->filesystem,
            $path,
        );
        $this->drafts = $container->get(ApprovalDraftService::class);
        $this->publisher = new ApprovalReleaseService($container->get(ApprovalReleaseStore::class), $this->compiler);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testPublishesImmutableContentAndReturnsTheSameRecordForARepeatedPublication(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);

        $first = $this->publisher->publish($draft->definitionId, $draft->revision, $content->hash, $context);
        $second = $this->publisher->publish($draft->definitionId, $draft->revision, $content->hash, $context);

        static::assertSame($first->id, $second->id);
        $stored = $this->publisher->read($first->id, $context);
        static::assertSame($draft->revision, $stored->getDraftRevision());
        static::assertSame($content->hash, $stored->getContentHash());
        static::assertSame($content->graphJson, $stored->getGraphJson());
        static::assertSame($content->contractJson, $stored->getContractJson());
        static::assertSame($content->graphPlanJson, $stored->getGraphPlanJson());
        static::assertSame($content->nodePlanJson, $stored->getNodePlanJson());
        static::assertSame($content->dependenciesJson, $stored->getDependenciesJson());
        static::assertSame($draft->revision, $this->drafts->read($draft->definitionId, $context)->revision);
    }

    public function testStaleDraftRevisionCannotPublishAnEarlierPreview(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $this->drafts->addNode($draft->definitionId, $draft->revision, $this->extraNode(), $context);
        $this->expectExceptionObject(ApprovalFlowException::draftRevisionConflict($draft->definitionId, 1, 2));

        $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);
    }

    public function testChangedArtifactMakesTheEarlierPreviewStaleWithoutWritingARecord(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $path = $this->directory . '/manifest.json';
        $manifest = json_decode($this->filesystem->readFile($path), associative: true, flags: \JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($this->directory . '/core.artifact', 'Changed test deployment.');
        $manifest['artifacts'][0]['sha256'] = hash('sha256', 'Changed test deployment.');
        $this->filesystem->dumpFile($path, json_encode($manifest, \JSON_THROW_ON_ERROR));
        try {
            $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);
            static::fail('Publication must compare the fresh content with the preview hash.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::PUBLICATION_CONTENT_CONFLICT, $exception->getErrorCode());
            static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
        }
        static::assertSame(0, $this->releaseCount($draft));
    }

    public function testPublishPermissionTakesPrecedenceOverDraftLookupAndArtifactLoading(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_draft:read']);
        $context = Context::createDefaultContext($source);
        $this->filesystem->remove($this->directory . '/manifest.json');
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_release:create']));

        $this->publisher->publish(Uuid::randomHex(), 1, str_repeat('a', 64), $context);
    }

    public function testReadPermissionTakesPrecedenceOverReleaseLookup(): void
    {
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_release:read']));

        $this->publisher->read(Uuid::randomHex(), $context);
    }

    public function testLaterDraftEditsDoNotChangeTheRelease(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $published = $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);

        $this->drafts->addNode($draft->definitionId, 1, $this->extraNode(), $context);

        static::assertSame($content->graphJson, $this->publisher->read($published->id, $context)->getGraphJson());
        static::assertSame(2, $this->drafts->read($draft->definitionId, $context)->revision);
    }

    public function testOrdinaryRepositoryWritesCannotChangeImmutableContent(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $release = $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository(ApprovalReleaseDefinition::ENTITY_NAME);
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledReleaseWrite());

        $repository->update([['id' => $release->id, 'graphJson' => '{}']], $context);
    }

    public function testSyncCannotBypassTheImmutableWriteBoundary(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $release = $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledReleaseWrite());

        static::getContainer()->get(EntityWriter::class)->sync([
            new SyncOperation('release', 'approval_flow_release', SyncOperation::ACTION_UPSERT, [
                ['id' => $release->id, 'graphJson' => '{}'],
            ]),
        ], WriteContext::createFromContext($context));
    }

    public function testEvenTheControlledScopeCannotUpdateOrDeleteARelease(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $release = $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository(ApprovalReleaseDefinition::ENTITY_NAME);
        try {
            $context->scope(ApprovalReleaseWriteGuard::WRITE_SCOPE, fn (Context $writeContext) => $repository->update([['id' => $release->id, 'contentHash' => str_repeat('b', 64)]], $writeContext));
            static::fail('The internal insert scope must not permit updates.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::UNCONTROLLED_RELEASE_WRITE, $exception->getErrorCode());
        }
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledReleaseWrite());

        $context->scope(ApprovalReleaseWriteGuard::WRITE_SCOPE, fn (Context $writeContext) => $repository->delete([['id' => $release->id]], $writeContext));
    }

    public function testADalEventFailureRollsBackTheRecordAndRestoresCallerScope(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $calls = 0;
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_release.written', static function (EntityWrittenEvent $event) use (&$calls): void {
            ++$calls;
            throw new \RuntimeException('Publication listener failed.');
        });
        try {
            $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);
            static::fail('An event failure must roll back publication.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Publication listener failed.', $exception->getMessage());
        }
        static::assertSame(1, $calls);
        static::assertSame(0, $this->releaseCount($draft));
        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
    }

    public function testPublicationRetainsTheOriginalContextAndAdminActor(): void
    {
        $userId = Uuid::randomHex();
        $systemContext = Context::createDefaultContext();
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $localeId = $registry->getRepository('locale')->searchIds((new Criteria())->setLimit(1), $systemContext)->firstId();
        $registry->getRepository('user')->create([[
            'id' => $userId,
            'localeId' => $localeId,
            'username' => $userId,
            'name' => 'Approval publisher',
            'password' => 'shopwell',
            'email' => $userId . '@example.com',
        ]], $systemContext);
        $source = new AdminApiSource($userId);
        $source->setPermissions(['approval_flow_definition:create', 'approval_flow_draft:create', 'approval_flow_draft:read', 'approval_flow_release:create', 'approval_flow_release:read']);
        $context = Context::createDefaultContext($source);
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $content = $this->compiler->compile($draft->graph);
        $observed = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_release.written', static function (EntityWrittenEvent $event) use (&$observed): void {
            $observed = $event->getContext();
        });

        $release = $this->publisher->publish($draft->definitionId, 1, $content->hash, $context);

        static::assertSame($context, $observed);
        static::assertSame($userId, $this->publisher->read($release->id, $context)->getCreatedById());
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    private function extraNode(): string
    {
        $graph = json_decode(GraphFixture::json(), associative: true, flags: \JSON_THROW_ON_ERROR);
        $node = $graph['nodes'][1];
        $node['id'] = 'extra';
        $node['config'] = new \stdClass();
        $node['extensions'] = new \stdClass();

        return json_encode($node, \JSON_THROW_ON_ERROR);
    }

    private function releaseCount(ApprovalDraft $draft): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_release WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($draft->definitionId)]);
    }
}
