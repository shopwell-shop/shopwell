<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1605530777PrivacyPolicyExtensionsToAppTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1605530777;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `app_translation`
            ADD `privacy_policy_extensions` MEDIUMTEXT NULL AFTER `description`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
