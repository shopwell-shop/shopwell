<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1656928097AddNewsletterRecipientEmailIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1656928097;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'newsletter_recipient', 'idx.newsletter_recipient.email')) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.newsletter_recipient.email` ON `newsletter_recipient` (`email`)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
