<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Webhook\Subscriber\WebhookSubscriberTest
 */
#[Package('framework')]
class WebhookStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function switchIntegration(string $oldIntegrationId, string $newIntegrationId): void
    {
        $this->connection->executeStatement(
            'UPDATE `webhook` SET `owner_integration_id` = :new WHERE `owner_integration_id` = :old',
            ['old' => $oldIntegrationId, 'new' => $newIntegrationId],
        );
    }

    /**
     * @param list<string> $roleIds
     *
     * @return array<string, list<string>> the names of the webhooks using each role, keyed by role id, for used roles only
     */
    public function getWebhookNamesByRole(array $roleIds): array
    {
        $webhookNames = [];
        foreach ($this->connection->fetchAllAssociative('SELECT `name`, `acl_role_ids` FROM `webhook` WHERE `acl_role_ids` IS NOT NULL ORDER BY `name`') as $webhook) {
            foreach (array_intersect($roleIds, Json::decodeToList($webhook['acl_role_ids'])) as $roleId) {
                $webhookNames[$roleId][] = $webhook['name'];
            }
        }

        return $webhookNames;
    }
}
