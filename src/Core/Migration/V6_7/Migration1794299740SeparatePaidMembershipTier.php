<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\AddColumnTrait;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794299740SeparatePaidMembershipTier extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1794299740;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'loyalty_account', 'paid_tier_id', 'BINARY(16)');

        if (!TableHelper::foreignKeyExists($connection, 'loyalty_account', 'fk.loyalty_account.paid_tier_id')) {
            $connection->executeStatement('ALTER TABLE `loyalty_account`
                ADD CONSTRAINT `fk.loyalty_account.paid_tier_id` FOREIGN KEY (`paid_tier_id`)
                REFERENCES `loyalty_tier` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        // A customer's effective tier may already be earned. Only a running subscription proves
        // which tier was bought; retrying the migration leaves an existing projection untouched.
        $connection->executeStatement('UPDATE `loyalty_account` a
            INNER JOIN `membership_subscription` s ON s.`customer_id` = a.`customer_id`
            SET a.`paid_tier_id` = s.`loyalty_tier_id`, a.`membership_paid_until` = s.`ends_at`
            WHERE a.`paid_tier_id` IS NULL
              AND s.`state` = \'active\'
              AND s.`starts_at` <= UTC_TIMESTAMP(3)
              AND s.`ends_at` > UTC_TIMESTAMP(3)
              AND s.`loyalty_tier_id` IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM `membership_subscription` newer
                  WHERE newer.`customer_id` = s.`customer_id`
                    AND newer.`state` = \'active\'
                    AND newer.`starts_at` <= UTC_TIMESTAMP(3)
                    AND (newer.`ends_at` > s.`ends_at`
                      OR (newer.`ends_at` = s.`ends_at` AND newer.`id` > s.`id`))
              )');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
