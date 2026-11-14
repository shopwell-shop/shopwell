<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow;

use Doctrine\DBAL\Connection;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisherRegistry;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowRequestCatalogueEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowPublicationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private WorkflowReleaseStore $releases;

    private WorkflowDraftStore $drafts;

    private PublicationTestPublisher $publisher;

    private Context $context;

    protected function setUp(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['workflow:read', 'workflow:publish', 'test:publish']);
        $this->context = new Context($source);
        $container = static::getContainer();
        $this->drafts = $container->get(WorkflowDraftStore::class);
        $this->publisher = new PublicationTestPublisher();
        $this->releases = new WorkflowReleaseStore($container->get(Connection::class), $this->drafts, $container->get('workflow_release.repository'), $container->get(WorkflowDocumentCodec::class), $container->get(WorkflowGraphDiagnostics::class), new WorkflowPublisherRegistry([$this->publisher]));
    }

    public function testSnapshotSurvivesDraftChangesAndDeletionAndRepeatedPublicationIsIdempotent(): void
    {
        $id = Uuid::randomHex();
        $document = $this->document();
        $this->drafts->create($id, 'Original', $document, $this->context);
        $release = $this->releases->publish($id, 1, 'test:approval', $this->context);
        static::assertSame($release->getId(), $this->releases->publish($id, 1, 'test:approval', $this->context)->getId());
        static::assertSame(1, $this->publisher->calls);
        $this->drafts->update($id, 'Edited', $document, 1, $this->context);
        $second = $this->releases->publish($id, 2, 'test:approval', $this->context);
        static::assertNotSame($release->getId(), $second->getId());
        static::assertSame(2, $second->getRevision());
        $this->drafts->delete($id, 2, $this->context);
        static::assertSame('Original', $this->releases->get($release->getId(), $this->context)->getName());
        static::assertSame($document, $this->releases->get($release->getId(), $this->context)->getDocument());
        static::assertSame(2, $this->releases->list($id, 25, 0, $this->context)->getTotal());
    }

    public function testRequestCatalogueContainsOnlyTheNewestReleaseOfExistingDrafts(): void
    {
        $id = Uuid::randomHex();
        $this->drafts->create($id, 'Original', $this->document(), $this->context);
        $this->releases->publish($id, 1, 'test:approval', $this->context);
        $this->drafts->update($id, 'Updated', $this->document(), 1, $this->context);
        $latest = $this->releases->publish($id, 2, 'test:approval', $this->context);
        $result = $this->releases->catalogue(25, 0, $this->context);
        static::assertContains($latest->getId(), $result['releases']->getIds());
        static::assertCount(1, $result['releases']->filter(static fn (WorkflowReleaseEntity $release): bool => $release->getDraftId() === $id));
        $this->drafts->delete($id, 2, $this->context);
        $after = $this->releases->catalogue(25, 0, $this->context);
        static::assertNotContains($latest->getId(), $after['releases']->getIds());
        static::assertSame($latest->getId(), $this->releases->get($latest->getId(), $this->context)->getId());
    }

    public function testBusinessCatalogueUsesItsEnabledRevisionBeforeCountingAndPagination(): void
    {
        $id = Uuid::randomHex();
        $document = str_replace('"numericKeys":{"0":"keep"}', '"requestAction":"payment:transfer"', $this->document());
        $this->drafts->create($id, 'Transfer', $document, $this->context);
        $enabled = $this->releases->publish($id, 1, 'test:approval', $this->context);
        $this->drafts->update($id, 'New transfer design', $document, 1, $this->context);
        $newer = $this->releases->publish($id, 2, 'test:approval', $this->context);
        $dispatcher = new EventDispatcher();
        $enabledId = $enabled->getId();
        $dispatcher->addListener(WorkflowRequestCatalogueEvent::class, static function (WorkflowRequestCatalogueEvent $event) use (&$enabledId): void {
            $event->restrictedActions['payment:transfer'] = $enabledId;
        });
        $c = static::getContainer();
        $store = new WorkflowReleaseStore($c->get(Connection::class), $this->drafts, $c->get('workflow_release.repository'), $c->get(WorkflowDocumentCodec::class), $c->get(WorkflowGraphDiagnostics::class), new WorkflowPublisherRegistry([$this->publisher]), $dispatcher);
        $result = $store->catalogue(1, 0, $this->context);
        static::assertSame(1, $result['total']);
        static::assertSame([$enabled->getId()], array_values($result['releases']->getIds()));
        static::assertSame([], $store->catalogue(1, 1, $this->context)['releases']->getIds());
        static::assertNotContains($newer->getId(), $result['releases']->getIds());
        $enabledId = null;
        static::assertSame(0, $store->catalogue(1, 0, $this->context)['total']);
    }

    public function testStalePublicationCannotPublishNewerUnreviewedChanges(): void
    {
        $id = Uuid::randomHex();
        $this->drafts->create($id, 'Original', $this->document(), $this->context);
        $this->drafts->update($id, 'Changed', $this->document(), 1, $this->context);
        $this->expectExceptionObject(WorkflowException::draftConflict($id));
        $this->releases->publish($id, 1, 'test:approval', $this->context);
    }

    public function testPublishedDraftIdentityCannotBeReusedAfterDeletion(): void
    {
        $id = Uuid::randomHex();
        $this->drafts->create($id, 'Original', $this->document(), $this->context);
        $this->releases->publish($id, 1, 'test:approval', $this->context);
        $this->drafts->delete($id, 1, $this->context);

        $this->expectExceptionObject(WorkflowException::draftConflict($id));
        $this->drafts->create($id, 'Different workflow', $this->document(), $this->context);
    }

    public function testInvalidGraphIsRejectedBeforeTheBusinessPublisherRuns(): void
    {
        $id = Uuid::randomHex();
        $this->drafts->create($id, 'Incomplete', '{"schemaVersion":1,"policy":"core:structured","nodes":[],"connections":[]}', $this->context);
        try {
            $this->releases->publish($id, 1, 'test:approval', $this->context);
            static::fail('Expected publication to be rejected');
        } catch (WorkflowException $exception) {
            static::assertSame('FRAMEWORK__WORKFLOW_PUBLICATION_REJECTED', $exception->getErrorCode());
        }
        static::assertSame(0, $this->publisher->calls);
        static::assertSame(0, $this->releases->list($id, 25, 0, $this->context)->getTotal());
    }

    public function testBusinessWritesRollbackIfPublicationFails(): void
    {
        $id = Uuid::randomHex();
        $this->drafts->create($id, 'Original', $this->document(), $this->context);
        $this->publisher->onPublish = function () use ($id): void {
            $this->drafts->update($id, 'Side effect', $this->document(), 1, $this->context);
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        };
        try {
            $this->releases->publish($id, 1, 'test:approval', $this->context);
            static::fail('Expected publication to be rejected');
        } catch (WorkflowException $exception) {
            static::assertSame('FRAMEWORK__WORKFLOW_PUBLICATION_REJECTED', $exception->getErrorCode());
        }
        static::assertSame('Original', $this->drafts->get($id, $this->context)->getName());
        static::assertSame(1, $this->drafts->get($id, $this->context)->getRevision());
        static::assertSame(0, $this->releases->list($id, 25, 0, $this->context)->getTotal());
    }

    public function testPublicationRequiresBothCoreAndBusinessPrivileges(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['workflow:publish']);
        $this->expectExceptionObject(WorkflowException::missingPublicationPrivilege('test:publish'));
        $this->releases->publish(Uuid::randomHex(), 1, 'test:approval', new Context($source));
    }

    public function testReleaseReadApiAndGenericWriteProtection(): void
    {
        $id = Uuid::randomHex();
        $document = $this->document();
        $this->drafts->create($id, 'Published', $document, $this->context);
        $release = $this->releases->publish($id, 1, 'test:approval', $this->context);
        $browser = $this->getBrowser(permissions: ['workflow:read', 'workflow_release:update', 'workflow_release:delete']);
        $browser->request('GET', '/api/_action/workflow/release/' . $release->getId());
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $body = json_decode((string) $browser->getResponse()->getContent(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $body);
        $schemaFile = (string) file_get_contents(\dirname(__DIR__, 5) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/workflow.json');
        $schemaPaths = json_decode($schemaFile, true, flags: \JSON_THROW_ON_ERROR);
        $responseSchema = json_decode(json_encode($schemaPaths['paths']['/_action/workflow/release/{id}']['get']['responses']['200']['content']['application/json']['schema'], \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $responseSchema);
        static::assertTrue((new Validator())->validate($body, $responseSchema)->isValid(), 'Served release response must match its OpenAPI schema.');
        static::assertSame($release->getId(), $body->id);
        static::assertEquals(json_decode($document, flags: \JSON_THROW_ON_ERROR), $body->document);
        $browser->request('GET', '/api/_action/workflow/releases?draftId=' . $id);
        static::assertSame(1, json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['total']);
        $browser->request('GET', '/api/_action/workflow/publishers');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/_action/workflow/draft/' . $id . '/publish', ['expectedRevision' => 1, 'publisher' => 'test:approval']);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('PATCH', '/api/workflow-release/' . $release->getId(), ['name' => 'Tampered']);
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        $browser->request('DELETE', '/api/workflow-release/' . $release->getId());
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        static::assertSame('Published', $this->releases->get($release->getId(), $this->context)->getName());
    }

    public function testParticipantCanReadCurrencyOptionsWithoutCurrencyAdministrationPermission(): void
    {
        $browser = $this->getBrowser(permissions: ['workflow:read', 'workflow_instance:start']);
        $browser->request('GET', '/api/_action/workflow/form-options/core:currency');
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $body = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertGreaterThan(0, \count($body['data']));
        foreach ($body['data'] as $option) {
            static::assertMatchesRegularExpression('/^[A-Z]{3}$/', $option['value']);
            static::assertStringContainsString($option['value'], $option['label']);
            static::assertSame(['value', 'label'], array_keys($option));
        }
        $browser->request('GET', '/api/_action/workflow/form-options/missing:source');
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/_action/workflow/request-catalogue');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testRequestCatalogueRequiresStartPermission(): void
    {
        $browser = $this->getBrowser(permissions: ['workflow:read']);
        $browser->request('GET', '/api/_action/workflow/request-catalogue');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testPublicationRejectsMalformedParametersAndUnavailableTargets(): void
    {
        $browser = $this->getBrowser(permissions: ['workflow:read', 'workflow:publish']);
        $id = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/_action/workflow/draft/' . $id . '/publish', ['expectedRevision' => '1', 'publisher' => 'missing:target']);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/_action/workflow/draft/' . $id . '/publish', ['expectedRevision' => 1, 'publisher' => 'missing:target']);
        static::assertSame(409, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/_action/workflow/release/' . $id);
        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    private function document(): string
    {
        return '{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"start","type":"core:start","typeVersion":1,"config":{"numericKeys":{"0":"keep"}}},{"id":"end","type":"core:end","typeVersion":1,"config":{}}],"connections":[{"id":"edge","source":"start","sourcePort":"out","target":"end","targetPort":"in"}]}';
    }
}

/**
 * @internal
 */
class PublicationTestPublisher implements WorkflowPublisher
{
    public int $calls = 0;

    public ?\Closure $onPublish = null;

    public function name(): string
    {
        return 'test:approval';
    }

    public function labelKey(): string
    {
        return 'test.approval';
    }

    public function privileges(): array
    {
        return ['test:publish'];
    }

    public function publish(WorkflowReleaseEntity $release, Context $context): void
    {
        ++$this->calls;
        if ($this->onPublish !== null) {
            ($this->onPublish)();
        }
    }
}
