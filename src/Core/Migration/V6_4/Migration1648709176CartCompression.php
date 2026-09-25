<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1648709176CartCompression extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1648709176;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'cart', 'compressed')) {
            $connection->executeStatement('ALTER TABLE `cart` ADD `compressed` tinyint(1) NOT NULL DEFAULT 0;');
        }

        // after adding the payload column, we may save carts as compressed serialized objects, there is no way of return at this point
        if (!TableHelper::columnExists($connection, 'cart', 'payload')) {
            $connection->executeStatement('ALTER TABLE `cart` ADD `payload` LONGBLOB NULL;');
        }

        if (!TableHelper::columnExists($connection, 'cart', 'cart')) {
            return;
        }

        do {
            $affectedRows = RetryableQuery::retryable($connection, static function () use ($connection): int {
                return (int) $connection->executeStatement(
                    'UPDATE cart SET `payload` = `cart` WHERE `payload` IS NULL AND `cart` IS NOT NULL LIMIT :limit',
                    ['limit' => self::UPDATE_LIMIT],
                    ['limit' => ParameterType::INTEGER]
                );
            });
        } while ($affectedRows === self::UPDATE_LIMIT);

        $this->dropColumnIfExists($connection, 'cart', 'cart');
    }
}
