<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;

/**
 * @internal
 *
 * Simulates the delegation mode of MCP clients such as Copilot: a non-app integration
 * authenticates with its own credentials and names a user in `sw-app-user-id`.
 */
#[Package('framework')]
class McpIntegrationDelegationTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testTheInitializeMetaNamesOnlyTheIntegrationWhenDelegating(): void
    {
        $integrationId = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $this->makeStandardIntegration($integrationId, ['currency:read'], ['shopwell-entity-search']);

        $userId = $this->createDelegatedUser(['currency:read'], ['shopwell-entity-search']);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_APP_USER_ID, $userId);

        [, $response] = $this->initialize($browser);

        static::assertSame(
            ['integration' => ['id' => $integrationId]],
            $response['result']['_meta']['shopwell'] ?? null,
        );
    }

    public function testADelegatedToolCallWorksWithTheIntersectedAllowlistAndPermissions(): void
    {
        $integrationId = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $this->makeStandardIntegration($integrationId, ['currency:read'], ['shopwell-entity-search']);

        $userId = $this->createDelegatedUser(['currency:read'], ['shopwell-entity-search']);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_APP_USER_ID, $userId);

        [$sessionId] = $this->initialize($browser);
        $response = $this->rpc($browser, $sessionId, 'tools/call', [
            'name' => 'shopwell-entity-search',
            'arguments' => ['entity' => 'currency', 'limit' => 1],
        ]);

        static::assertArrayNotHasKey('error', $response, json_encode($response, \JSON_THROW_ON_ERROR));
        $result = $response['result'] ?? null;
        static::assertIsArray($result);
        static::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        static::assertArrayHasKey('content', $result);
        static::assertNotSame([], $result['content']);
    }

    public function testADelegatedToolCallIsRejectedWhenTheNamedUserDoesNotAllowTheTool(): void
    {
        $integrationId = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $this->makeStandardIntegration($integrationId, ['currency:read'], ['shopwell-entity-search']);

        $userId = $this->createDelegatedUser(['currency:read'], []);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_APP_USER_ID, $userId);

        [$sessionId] = $this->initialize($browser);
        $response = $this->rpc($browser, $sessionId, 'tools/call', [
            'name' => 'shopwell-entity-search',
            'arguments' => ['entity' => 'currency', 'limit' => 1],
        ]);

        static::assertArrayHasKey('error', $response, json_encode($response, \JSON_THROW_ON_ERROR));
    }

    public function testACachedResultOfADelegatedUserIsOnlyReadableForThatUser(): void
    {
        $integrationId = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $this->makeStandardIntegration($integrationId, ['currency:read'], ['shopwell-entity-search']);

        $userA = $this->createDelegatedUser(['currency:read'], ['shopwell-entity-search']);
        $userB = $this->createDelegatedUser(['currency:read'], ['shopwell-entity-search']);

        $pointer = static::getContainer()->get(ToolResultCacheStorage::class)
            ->storeFor('admin:' . $integrationId . ':' . $userA, '{"success":true,"data":[1]}');

        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_APP_USER_ID, $userA);
        [$sessionId] = $this->initialize($browser);
        $read = $this->rpc($browser, $sessionId, 'resources/read', ['uri' => $pointer->uri()]);
        static::assertSame('{"success":true,"data":[1]}', $read['result']['contents'][0]['text'] ?? null);

        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_APP_USER_ID, $userB);
        [$sessionId] = $this->initialize($browser);
        $response = $this->rpc($browser, $sessionId, 'resources/read', ['uri' => $pointer->uri()]);
        static::assertArrayHasKey('error', $response, 'another delegated user must not read the result');

        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_APP_USER_ID, '');
        [$sessionId] = $this->initialize($browser);
        $response = $this->rpc($browser, $sessionId, 'resources/read', ['uri' => $pointer->uri()]);
        static::assertArrayHasKey('error', $response, 'the integration itself must not read a per-user result');
    }

    /**
     * @param list<string> $privileges
     * @param list<string> $tools
     */
    private function makeStandardIntegration(string $integrationId, array $privileges, array $tools): void
    {
        $roleId = Uuid::randomBytes();
        $this->connection()->insert('acl_role', [
            'id' => $roleId,
            'name' => 'delegation integration role',
            'privileges' => \json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection()->insert('integration_role', [
            'acl_role_id' => $roleId,
            'integration_id' => Uuid::fromHexToBytes($integrationId),
        ]);

        $this->connection()->update('integration', [
            'admin' => 0,
            'mcp_allowlist' => \json_encode(['tools' => $tools, 'resources' => [], 'prompts' => []], \JSON_THROW_ON_ERROR),
        ], ['id' => Uuid::fromHexToBytes($integrationId)]);
    }

    /**
     * @param list<string> $privileges
     * @param list<string> $tools
     */
    private function createDelegatedUser(array $privileges, array $tools): string
    {
        $user = TestUser::createNewTestUser($this->connection());
        $userId = Uuid::fromHexToBytes($user->getUserId());

        $roleId = Uuid::randomBytes();
        $this->connection()->insert('acl_role', [
            'id' => $roleId,
            'name' => 'delegated user role',
            'privileges' => \json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection()->insert('acl_user_role', [
            'user_id' => $userId,
            'acl_role_id' => $roleId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection()->update('user', [
            'admin' => 0,
            'mcp_allowlist' => \json_encode(['tools' => $tools, 'resources' => [], 'prompts' => []], \JSON_THROW_ON_ERROR),
        ], ['id' => $userId]);

        return $user->getUserId();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function initialize(TestBrowser $browser): array
    {
        $response = $this->rpc($browser, null, 'initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'delegation-test', 'version' => '1.0'],
        ]);

        $sessionId = $browser->getResponse()->headers->get('Mcp-Session-Id');
        static::assertIsString($sessionId, 'initialize did not return an MCP session id');

        return [$sessionId, $response];
    }

    /**
     * @param array<string, mixed>|\stdClass $params
     *
     * @return array<string, mixed>
     */
    private function rpc(TestBrowser $browser, ?string $sessionId, string $method, array|\stdClass $params): array
    {
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ]),
            \json_encode([
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        static::assertSame(
            200,
            $browser->getResponse()->getStatusCode(),
            \sprintf('MCP "%s" returned non-200: %s', $method, (string) $browser->getResponse()->getContent()),
        );

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content);

        $decoded = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
