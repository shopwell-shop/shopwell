<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1629877210UpdateWebhookNameAndSerializedWebhookMessageOfWebhookEventLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1629877210;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `webhook_event_log` MODIFY COLUMN `webhook_name` TEXT NOT NULL;');
        $connection->executeStatement('ALTER TABLE `webhook_event_log` MODIFY COLUMN `serialized_webhook_message` LONGBLOB NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
