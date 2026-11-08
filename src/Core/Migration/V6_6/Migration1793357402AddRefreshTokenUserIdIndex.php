<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1793357402AddRefreshTokenUserIdIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793357402;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::indexExists($connection, 'refresh_token', 'idx.refresh_token.user_id')) {
            $connection->executeStatement('CREATE INDEX `idx.refresh_token.user_id` ON `refresh_token` (`user_id`)');
        }
    }
}
