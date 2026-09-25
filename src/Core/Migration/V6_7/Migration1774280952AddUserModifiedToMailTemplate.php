<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1774280952AddUserModifiedToMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774280952;
    }

    public function update(Connection $connection): void
    {
        $addedColumn = $this->addColumn(
            $connection,
            table: 'mail_template',
            column: 'was_modified_by_user',
            type: 'TINYINT(1)',
            nullable: false,
            default: '0',
        );

        if ($addedColumn && !$this->isInstallation()) {
            $connection->executeStatement('UPDATE `mail_template` SET `was_modified_by_user` = 1');
        }
    }
}
