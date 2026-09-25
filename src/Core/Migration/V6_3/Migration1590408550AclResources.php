<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1590408550AclResources extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1590408550;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'acl_resource')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `acl_role` ADD `privileges` json NULL AFTER `name`;');

        foreach ($this->getRoles($connection) as $id => $privs) {
            $list = array_column($privs, 'priv');

            $connection->executeStatement(
                'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = :id',
                [
                    'privileges' => json_encode($list, \JSON_THROW_ON_ERROR),
                    'id' => Uuid::fromHexToBytes($id),
                ]
            );
        }

        $connection->executeStatement('ALTER TABLE `acl_role` CHANGE `privileges` `privileges` json NOT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE `acl_resource`');
    }

    /**
     * @return array<string, list<array{priv: string}>>
     */
    private function getRoles(Connection $connection): array
    {
        $roles = $connection->fetchAllAssociative('
            SELECT LOWER(HEX(`role`.id)) as id, CONCAT(`resource`.`resource`, \':\', `resource`.`privilege`) as priv
            FROM acl_role `role`
                LEFT JOIN acl_resource `resource`
                    ON `role`.id = `resource`.acl_role_id
        ');

        /** @var array<string, list<array{priv: string}>> $grouped */
        $grouped = FetchModeHelper::group($roles);

        return $grouped;
    }
}
