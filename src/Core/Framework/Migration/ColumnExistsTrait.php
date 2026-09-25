<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Deprecation\BCChange\ExceptionChange;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Util\Database\TableHelper;

trait ColumnExistsTrait
{
    /**
     * @param non-empty-string $table
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [], description: 'Will no longer throw TableNotFoundException for a missing table but return false.')]
    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        if (Feature::isActive('v6.8.0.0')) {
            return TableHelper::columnExists($connection, $table, $column);
        }

        return (bool) $connection->fetchOne(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );
    }
}
