<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftEntity;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftStore;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalDraftServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testPersistsTheCanonicalGraphAndAdvancesTheRevisionOnEdit(): void
    {
        $context = $this->designerContext();
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);

        static::assertSame(1, $draft->revision);
        static::assertSame($draft->graph->json, $service->read($draft->definitionId, $context)->graph->json);
        static::assertSame('Transfer approval', static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT name FROM approval_flow_definition WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($draft->definitionId)],
        ));

        $edit = $service->addNode($draft->definitionId, 1, self::reviewNode('second-review'), $context);
        $reloaded = $service->read($draft->definitionId, $context);

        static::assertSame(2, $edit->revision);
        static::assertSame(2, $reloaded->revision);
        static::assertSame($edit->mutation->graph->json, $reloaded->graph->json);
        static::assertStringContainsString('"id":"second-review"', $reloaded->graph->json);
        static::assertStringContainsString('"config":{}', $reloaded->graph->json);
    }

    public function testStaleRevisionCannotOverwriteTheFirstEdit(): void
    {
        $context = $this->designerContext();
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);
        $firstEdit = $service->addNode($draft->definitionId, 1, self::reviewNode('first-edit'), $context);

        try {
            $service->addNode($draft->definitionId, 1, self::reviewNode('stale-edit'), $context);
            static::fail('Expected a revision conflict.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(409, $exception->getStatusCode());
        }

        $reloaded = $service->read($draft->definitionId, $context);
        static::assertSame(2, $reloaded->revision);
        static::assertSame($firstEdit->mutation->graph->json, $reloaded->graph->json);
    }

    public function testRemovingANodeRequiresConfirmationAtThePreviewedRevision(): void
    {
        $context = $this->designerContext();
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);
        $diagnostics = GraphFixture::diagnostics();
        $mutator = new GraphMutator(GraphFixture::codec($diagnostics), $diagnostics, new GraphJsonDecoder());
        $preview = $mutator->removeNode($draft->graph, 'review');

        try {
            $service->removeNode($draft->definitionId, 1, 'review', confirmRemoval: false, context: $context);
            static::fail('Expected removal to require confirmation.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::REMOVAL_NOT_CONFIRMED, $exception->getErrorCode());
        }
        static::assertSame(1, $service->read($draft->definitionId, $context)->revision);

        $edit = $service->removeNode($draft->definitionId, 1, 'review', confirmRemoval: true, context: $context);

        static::assertCount(2, $edit->mutation->removedConnections);
        static::assertSame($preview->removedNodes, $edit->mutation->removedNodes);
        static::assertSame($preview->graph->json, $service->read($draft->definitionId, $context)->graph->json);
    }

    public function testMissingUpdatePrivilegeTakesPrecedenceOverDraftLookup(): void
    {
        $context = $this->designerContext(['approval_flow_draft:read']);
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_draft:update']));

        $this->service()->addNode(Uuid::randomHex(), 1, self::reviewNode('second-review'), $context);
    }

    public function testAnInvalidEditLeavesGraphAndRevisionUnchanged(): void
    {
        $context = $this->designerContext();
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);

        try {
            $service->addNode($draft->definitionId, 1, self::reviewNode('review'), $context);
            static::fail('Expected the duplicate node to be rejected.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_GRAPH, $exception->getErrorCode());
        }

        $reloaded = $service->read($draft->definitionId, $context);
        static::assertSame(1, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
    }

    public function testDirectRepositoryUpdateCannotBypassRevisionChecks(): void
    {
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), Context::createDefaultContext());
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_draft');

        try {
            $repository->update([['definitionId' => $draft->definitionId, 'revision' => 20]], Context::createDefaultContext());
            static::fail('Expected a controlled-write rejection.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::UNCONTROLLED_DRAFT_WRITE, $exception->getErrorCode());
        }

        static::assertSame(1, $service->read($draft->definitionId, Context::createDefaultContext())->revision);
    }

    public function testDirectRepositoryDeleteCannotDiscardADraft(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->service()->create('Transfer approval', GraphFixture::json(), $context);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_draft');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledDraftWrite());

        $repository->delete([['definitionId' => $draft->definitionId]], $context);
    }

    public function testDirectRepositoryCreateCannotBypassGraphAdmission(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_definition');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledDraftWrite());

        $repository->create([['name' => 'Uncontrolled']], Context::createDefaultContext());
    }

    public function testSyncCannotBypassTheControlledWriteBoundary(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->service()->create('Transfer approval', GraphFixture::json(), $context);
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledDraftWrite());

        static::getContainer()->get(EntityWriter::class)->sync([
            new SyncOperation('draft', 'approval_flow_draft', SyncOperation::ACTION_UPSERT, [
                ['definitionId' => $draft->definitionId, 'revision' => 42],
            ]),
        ], WriteContext::createFromContext($context));
    }

    public function testPersistsAuthoredStringsWithoutHtmlSanitization(): void
    {
        $payload = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $payload);
        static::assertInstanceOf(\stdClass::class, $payload->meta);
        $payload->meta->name = '<strong>转账 & "审批"</strong>';
        $json = json_encode($payload, \JSON_THROW_ON_ERROR);
        $context = Context::createDefaultContext();
        $service = $this->service();

        $draft = $service->create('Transfer approval', $json, $context);
        $reloaded = $service->read($draft->definitionId, $context);
        $storedPayload = json_decode($reloaded->graph->json, flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $storedPayload);
        static::assertInstanceOf(\stdClass::class, $storedPayload->meta);

        static::assertSame('<strong>转账 & "审批"</strong>', $storedPayload->meta->name);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
    }

    public function testRemovalConfirmationDoesNotApplyToANewerRevision(): void
    {
        $service = $this->service();
        $context = Context::createDefaultContext();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);
        $firstEdit = $service->addNode($draft->definitionId, 1, self::reviewNode('new-review'), $context);

        try {
            $service->removeNode($draft->definitionId, 1, 'review', confirmRemoval: true, context: $context);
            static::fail('Expected confirmation for an older revision to be rejected.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::DRAFT_REVISION_CONFLICT, $exception->getErrorCode());
        }

        static::assertSame($firstEdit->mutation->graph->json, $service->read($draft->definitionId, $context)->graph->json);
    }

    public function testCreateRollsBackTheDefinitionWhenItsDraftWriteFails(): void
    {
        $name = 'Rejected ' . Uuid::randomHex();
        $failure = new \RuntimeException('Draft write rejected.');
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            PreWriteValidationEvent::class,
            static function (PreWriteValidationEvent $event) use ($failure): void {
                if ($event->getCommandsForEntity('approval_flow_draft') !== []) {
                    throw $failure;
                }
            },
        );

        try {
            $this->service()->create($name, GraphFixture::json(), Context::createDefaultContext());
            static::fail('Expected a write failure.');
        } catch (\RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }

        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM approval_flow_definition WHERE name = :name',
            ['name' => $name],
        ));
    }

    public function testAWriteEventFailureRollsBackTheGraphAndRevisionAndRestoresTheScope(): void
    {
        $context = $this->designerContext();
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);
        $failure = new \RuntimeException('Post-write event failed.');
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            'approval_flow_draft.written',
            static function () use ($failure): void {
                throw $failure;
            },
        );

        try {
            $service->addNode($draft->definitionId, 1, self::reviewNode('rejected'), $context);
            static::fail('Expected the post-write failure.');
        } catch (\RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }

        $reloaded = $service->read($draft->definitionId, $context);
        static::assertSame(1, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    public function testConnectionsCanBeRemovedAndRestoredThroughTheWriteOwner(): void
    {
        $service = $this->service();
        $context = $this->designerContext();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);

        $removed = $service->removeConnection($draft->definitionId, 1, 'review-end', confirmRemoval: true, context: $context);
        $reconnected = $service->addConnection($draft->definitionId, 2, $removed->mutation->removedConnections[0], $context);

        static::assertSame(3, $reconnected->revision);
        static::assertSame($draft->graph->json, $service->read($draft->definitionId, $context)->graph->json);
    }

    public function testMissingPluginKeepsTheStoredConfigurationReadableAndBlocksChanges(): void
    {
        $filesystem = new Filesystem();
        $fixtureDirectory = \dirname(__DIR__, 5) . '/unit/Core/Framework/ApprovalFlow/Graph';
        $loader = new YamlNodeTypeLoader($filesystem, [new NodeTypeSourceDirectory('finance', $fixtureDirectory . '/Type/Registry/_fixtures')]);
        $diagnostics = GraphFixture::diagnostics([$loader]);
        $codec = GraphFixture::codec($diagnostics);
        $service = new ApprovalDraftService(
            static::getContainer()->get(ApprovalDraftStore::class),
            $codec,
            $diagnostics,
            new GraphMutator($codec, $diagnostics, new GraphJsonDecoder()),
        );
        $context = $this->designerContext();
        $json = $filesystem->readFile($fixtureDirectory . '/_fixtures/finance-transfer.json');

        $draft = $service->create('Plugin transfer', $json, $context);
        $withPlugin = $service->read($draft->definitionId, $context);
        $withoutPlugin = $this->service()->read($draft->definitionId, $context);

        static::assertTrue($withPlugin->diagnostics->isWritable());
        static::assertFalse($withoutPlugin->diagnostics->isWritable());
        static::assertSame($withPlugin->graph->json, $withoutPlugin->graph->json);
        foreach (['"limit":0', '"enabled":false', '"note":null', '"options":{}', '"groups":[]'] as $fragment) {
            static::assertStringContainsString($fragment, $withoutPlugin->graph->json);
        }

        try {
            $this->service()->removeNode($draft->definitionId, 1, 'review', confirmRemoval: true, context: $context);
            static::fail('Expected missing capability to block the write.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_GRAPH, $exception->getErrorCode());
        }

        static::assertSame(1, $service->read($draft->definitionId, $context)->revision);
        $restored = $service->removeNode($draft->definitionId, 1, 'review', confirmRemoval: true, context: $context);
        static::assertSame(2, $restored->revision);
        static::assertStringContainsString('"options":{}', $restored->mutation->removedNodes[0]);
    }

    public function testCustomerContextCannotReadAnApprovalDraft(): void
    {
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_draft:read']));

        $this->service()->read(Uuid::randomHex(), $context);
    }

    public function testDalEventsAndPersistedActorKeepTheCallerIdentity(): void
    {
        $context = $this->designerContext();
        $contexts = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            'approval_flow_draft.written',
            static function (EntityWrittenEvent $event) use (&$contexts): void {
                $contexts[] = $event->getContext();
            },
        );
        $service = $this->service();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);

        $service->addNode($draft->definitionId, 1, self::reviewNode('new-review'), $context);

        $stored = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_draft')
            ->search(new Criteria([$draft->definitionId]), $context)->first();
        static::assertInstanceOf(ApprovalDraftEntity::class, $stored);
        static::assertInstanceOf(AdminApiSource::class, $context->getSource());
        static::assertSame($context->getSource()->getUserId(), $stored->getUpdatedById());
        static::assertSame([$context, $context], $contexts);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    /**
     * @param list<string> $privileges
     */
    private function designerContext(array $privileges = ['approval_flow_definition:create', 'approval_flow_draft:create', 'approval_flow_draft:read', 'approval_flow_draft:update']): Context
    {
        $userId = Uuid::randomHex();
        $systemContext = Context::createDefaultContext();
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $localeId = $registry->getRepository('locale')->searchIds((new Criteria())->setLimit(1), $systemContext)->firstId();
        $registry->getRepository('user')->create([[
            'id' => $userId,
            'localeId' => $localeId,
            'username' => $userId,
            'name' => 'Approval designer',
            'password' => 'shopwell',
            'email' => $userId . '@example.com',
        ]], $systemContext);
        $source = new AdminApiSource($userId);
        $source->setPermissions($privileges);

        return Context::createDefaultContext($source);
    }

    private static function reviewNode(string $id): string
    {
        $graph = json_decode(GraphFixture::json(), true, flags: \JSON_THROW_ON_ERROR);
        $node = $graph['nodes'][1];
        $node['id'] = $id;
        $node['config'] = new \stdClass();
        $node['extensions'] = new \stdClass();

        return json_encode($node, \JSON_THROW_ON_ERROR);
    }

    private function service(): ApprovalDraftService
    {
        return static::getContainer()->get(ApprovalDraftService::class);
    }
}
