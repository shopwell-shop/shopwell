<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Webhook\EventLog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopwell\Core\Test\Integration\Builder\Webhook\WebhookBuilder;
use Shopwell\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class WebhookEventLogApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testAUserOnlyReadsTheLogOfWebhooksTheyOwn(): void
    {
        $user = TestUser::createNewTestUser($this->connection, ['webhook_event_log:read']);

        $ownLogId = $this->createLog(WebhookBuilder::ownedByUser($user->getUserId())->insert($this->connection));
        $this->createLog(WebhookBuilder::ownedByUser(TestUser::createNewTestUser($this->connection)->getUserId())->insert($this->connection));

        $user->authorizeBrowser($this->getBrowser());

        static::assertSame([$ownLogId], $this->searchLogIds());
    }

    public function testAnAdminReadsTheLogOfEveryWebhook(): void
    {
        $userLogId = $this->createLog(WebhookBuilder::ownedByUser(TestUser::createNewTestUser($this->connection)->getUserId())->insert($this->connection));
        $appLogId = $this->createLog(WebhookBuilder::ofApp($this->createApp([])->getId())->insert($this->connection));

        $logIds = $this->searchLogIds();

        static::assertContains($userLogId, $logIds);
        static::assertContains($appLogId, $logIds);
    }

    public function testAnAppOnlyReadsTheLogOfItsOwnWebhooks(): void
    {
        $app = $this->createApp(['webhook_event_log:read']);
        $manifestLogId = $this->createLog(WebhookBuilder::ofApp($app->getId())->insert($this->connection));
        $apiLogId = $this->createLog(WebhookBuilder::ownedByIntegration($app->getIntegrationId())->insert($this->connection));
        $this->createLog(WebhookBuilder::ofApp($this->createApp([])->getId())->insert($this->connection));

        $this->authorizeBrowserWithIntegration($this->getBrowser(), $app->getIntegrationId());

        static::assertEqualsCanonicalizing([$manifestLogId, $apiLogId], $this->searchLogIds());
    }

    public function testAnAppActingForAUserDoesNotReadTheLogOfTheUsersWebhooks(): void
    {
        $app = $this->createApp(['webhook_event_log:read']);
        $appLogId = $this->createLog(WebhookBuilder::ofApp($app->getId())->insert($this->connection));

        $user = TestUser::createNewTestUser($this->connection, ['webhook_event_log:read', 'app.all']);
        $this->createLog(WebhookBuilder::ownedByUser($user->getUserId())->insert($this->connection));

        $this->authorizeBrowserWithIntegration($this->getBrowser(), $app->getIntegrationId());

        static::assertSame([$appLogId], $this->searchLogIds(['HTTP_SW_APP_USER_ID' => $user->getUserId()]));
    }

    public function testTheLogOfADeletedWebhookIsOnlyReadByAnAdmin(): void
    {
        $user = TestUser::createNewTestUser($this->connection, ['webhook_event_log:read']);
        $webhookId = WebhookBuilder::ownedByUser($user->getUserId())->insert($this->connection);
        $logId = $this->createLog($webhookId);

        $this->connection->delete('webhook', ['id' => Uuid::fromHexToBytes($webhookId)]);

        static::assertContains($logId, $this->searchLogIds());

        $user->authorizeBrowser($this->getBrowser());

        static::assertSame([], $this->searchLogIds());
    }

    public function testAWebhookRecreatedWithTheIdOfADeletedOneDoesNotReadItsLog(): void
    {
        $webhookId = WebhookBuilder::ownedByUser(TestUser::createNewTestUser($this->connection)->getUserId())->insert($this->connection);
        $this->createLog($webhookId);

        $this->connection->delete('webhook', ['id' => Uuid::fromHexToBytes($webhookId)]);

        $user = TestUser::createNewTestUser($this->connection, ['webhook_event_log:read']);
        WebhookBuilder::ownedByUser($user->getUserId())->id($webhookId)->insert($this->connection);

        $user->authorizeBrowser($this->getBrowser());

        static::assertSame([], $this->searchLogIds());
    }

    public function testAggregationsOnlyCountTheLogAUserMayRead(): void
    {
        $user = TestUser::createNewTestUser($this->connection, ['webhook_event_log:read']);

        $this->createLog(WebhookBuilder::ownedByUser($user->getUserId())->insert($this->connection));
        $this->createLog(WebhookBuilder::ownedByUser(TestUser::createNewTestUser($this->connection)->getUserId())->insert($this->connection));

        $user->authorizeBrowser($this->getBrowser());
        $this->getBrowser()->jsonRequest('POST', '/api/aggregate/webhook-event-log', [
            'aggregations' => [['name' => 'logs', 'type' => 'count', 'field' => 'id']],
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(1, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['aggregations']['logs']['count']);
    }

    /**
     * @param array<string, string> $server
     *
     * @return list<string>
     */
    private function searchLogIds(array $server = []): array
    {
        $this->getBrowser()->jsonRequest('POST', '/api/search-ids/webhook-event-log', [], $server);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
    }

    /**
     * @param list<string> $privileges
     */
    private function createApp(array $privileges): AppEntity
    {
        return (new AppFixture(static::getContainer()->get('app.repository')))->createAppFromData([
            'aclRole' => ['name' => 'app-' . Uuid::randomHex(), 'privileges' => $privileges],
        ]);
    }

    private function createLog(string $webhookId): string
    {
        $logId = Uuid::randomHex();
        $this->connection->insert('webhook_event_log', [
            'id' => Uuid::fromHexToBytes($logId),
            'webhook_id' => Uuid::fromHexToBytes($webhookId),
            'delivery_status' => WebhookEventLogDefinition::STATUS_SUCCESS,
            'webhook_name' => 'webhook',
            'event_name' => 'product.written',
            'url' => 'https://example.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $logId;
    }
}
