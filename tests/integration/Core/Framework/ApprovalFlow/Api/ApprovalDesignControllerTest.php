<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalDesignControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testPersonalApprovalAccessDoesNotGrantDesignAccess(): void
    {
        $browser = $this->getBrowser(permissions: ['approval_flow:access']);
        $browser->request('GET', '/api/_action/approval-flow/design/catalog');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/_action/approval-flow/design/diagnose', ['graphJson' => GraphFixture::json()]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testAuthenticationIsRequired(): void
    {
        $browser = $this->getBrowser(authorized: false);
        $browser->request('GET', '/api/_action/approval-flow/design/catalog');
        static::assertSame(401, $browser->getResponse()->getStatusCode());
    }

    public function testRegistryDiscoveryAndGraphDiagnosticsRemainDesignOperations(): void
    {
        $browser = $this->getBrowser(permissions: ['approval_flow_definition:read']);
        $browser->request('GET', '/api/_action/approval-flow/design/catalog');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $catalog = json_decode((string) $browser->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains('core:approval@1.0.0', array_column($catalog['nodeTypes'], 'key'));

        $browser->jsonRequest('POST', '/api/_action/approval-flow/design/diagnose', ['graphJson' => GraphFixture::json()]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertJsonStringEqualsJsonString('[]', (string) $browser->getResponse()->getContent());

        $browser->jsonRequest('POST', '/api/_action/approval-flow/design/diagnose', ['graphJson' => '{}']);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $diagnostics = json_decode((string) $browser->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertNotSame([], $diagnostics);
        static::assertArrayHasKey('path', $diagnostics[0]);
        static::assertArrayHasKey('code', $diagnostics[0]);
    }

    public function testDefinitionsAndDraftsUseTheStandardDalApi(): void
    {
        $context = Context::createDefaultContext();
        $name = 'DAL definition ' . Uuid::randomHex();
        $draft = static::getContainer()->get(ApprovalDraftService::class)->create($name, GraphFixture::json(), $context);
        $browser = $this->getBrowser(permissions: ['approval_flow_definition:read', 'approval_flow_draft:read', 'approval_flow_release:read']);

        $browser->jsonRequest('POST', '/api/search/approval-flow-definition', [
            'filter' => [['type' => 'equals', 'field' => 'id', 'value' => $draft->definitionId]],
            'associations' => ['draft' => []],
            'includes' => [
                'approval_flow_definition' => ['id', 'name', 'draft'],
                'approval_flow_draft' => ['definitionId', 'revision', 'createdAt', 'updatedAt'],
            ],
        ]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $result = json_decode((string) $browser->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($draft->definitionId, $result['data'][0]['id']);
        static::assertSame($draft->definitionId, $result['data'][0]['relationships']['draft']['data']['id']);
        static::assertSame('approval_flow_draft', $result['included'][0]['type']);
        static::assertSame(1, $result['included'][0]['attributes']['revision']);
        static::assertArrayNotHasKey('graphJson', $result['included'][0]['attributes']);
        $browser->request('GET', '/api/approval-flow-draft/' . $draft->definitionId);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $result = json_decode((string) $browser->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(1, $result['data']['attributes']['revision']);
        static::assertSame($draft->graph->json, $result['data']['attributes']['graphJson']);
    }
}
