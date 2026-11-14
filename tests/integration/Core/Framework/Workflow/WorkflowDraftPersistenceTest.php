<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowDraftPersistenceTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testRoundTripPreservesUnknownConfigurationAndRejectsStaleUpdatesAndDeletes(): void
    {
        $browser = $this->getBrowser(permissions: ['workflow:create', 'workflow:read', 'workflow:update', 'workflow:delete']);
        $id = Uuid::randomHex();
        $document = ['schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [
            ['id' => 'custom', 'type' => 'example:unknown', 'typeVersion' => 1, 'config' => ['html' => '<b>Keep me</b>', 'map' => (object) ['0' => 'zero']]],
        ], 'connections' => []];
        $browser->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $id, 'name' => 'First draft', 'document' => $document]);
        static::assertSame(201, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $created = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(1, $created['revision']);
        $browser->request('GET', '/api/_action/workflow/draft/' . $id);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $loaded = json_decode((string) $browser->getResponse()->getContent(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $loaded);
        static::assertEquals(json_decode(json_encode($document, \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR), $loaded->document);
        $browser->jsonRequest('PUT', '/api/_action/workflow/draft/' . $id, ['name' => 'Renamed', 'document' => $document, 'expectedRevision' => 1]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame(2, json_decode((string) $browser->getResponse()->getContent(), true)['revision']);
        $browser->jsonRequest('PUT', '/api/_action/workflow/draft/' . $id, ['name' => 'Stale overwrite', 'document' => $document, 'expectedRevision' => 1]);
        static::assertSame(409, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('DELETE', '/api/_action/workflow/draft/' . $id, ['expectedRevision' => 1]);
        static::assertSame(409, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/_action/workflow/draft/' . $id);
        static::assertSame('Renamed', json_decode((string) $browser->getResponse()->getContent(), true)['name']);
        $browser->jsonRequest('DELETE', '/api/_action/workflow/draft/' . $id, ['expectedRevision' => 2]);
        static::assertSame(204, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/_action/workflow/draft/' . $id);
        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    public function testPermissionsAndGenericDalWritesCannotBypassTheRevisionCheck(): void
    {
        $id = Uuid::randomHex();
        $payload = ['id' => $id, 'name' => 'Protected', 'document' => ['schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [], 'connections' => []]];
        $browser = $this->getBrowser(permissions: ['workflow:read']);
        $browser->jsonRequest('POST', '/api/_action/workflow/draft', $payload);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $this->resetBrowser();
        $browser = $this->getBrowser(permissions: ['workflow:create', 'workflow:read']);
        $browser->jsonRequest('POST', '/api/_action/workflow/draft', $payload);
        static::assertSame(201, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('PUT', '/api/_action/workflow/draft/' . $id, ['name' => 'Denied', 'document' => $payload['document'], 'expectedRevision' => 1]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('DELETE', '/api/_action/workflow/draft/' . $id, ['expectedRevision' => 1]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $this->resetBrowser();
        $browser = $this->getBrowser(permissions: ['workflow_draft:read', 'workflow_draft:update', 'workflow_draft:delete']);
        $browser->jsonRequest('PATCH', '/api/workflow-draft/' . $id, ['name' => 'Bypass']);
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        $browser->request('DELETE', '/api/workflow-draft/' . $id);
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/search/workflow-draft', ['ids' => [$id]]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testApproverLookupPermissionsAllowActiveUserSearchButForbidUserUpdates(): void
    {
        $browser = $this->getBrowser(permissions: ['workflow:read', 'workflow_draft:read', 'user:read']);

        $browser->jsonRequest('POST', '/api/search/user', [
            'limit' => 10,
            'filter' => [['type' => 'equals', 'field' => 'active', 'value' => true]],
        ]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $browser->jsonRequest('PATCH', '/api/user/' . Uuid::randomHex(), ['name' => 'Forbidden update']);
        static::assertSame(403, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testMalformedDocumentsAndRevisionTokensAreRejected(): void
    {
        $browser = $this->getBrowser(permissions: ['workflow:create', 'workflow:update']);
        $id = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $id, 'name' => 'Invalid', 'document' => ['nodes' => []]]);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('PUT', '/api/_action/workflow/draft/' . $id, ['name' => 'Invalid', 'document' => [], 'expectedRevision' => '1']);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }
}
