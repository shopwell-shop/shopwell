<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the memberships a customer holds.
 *
 * A membership order records what was bought and never changes; this table records the membership
 * itself, which does change: a renewal of the same tier extends the running one, and the end of a
 * membership is an event somebody has to act on. Without a row here, "is this customer a member"
 * could only be answered by looking at the last order and comparing dates, and an expired membership
 * would never be noticed by anything but the next page view.
 *
 * `ends_at` is indexed because the daily task looks for exactly the memberships that ran out.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793603497CreateMembershipSubscriptionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793603497;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `membership_subscription` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `membership_plan_id` BINARY(16) NULL,
                `loyalty_tier_id` BINARY(16) NULL,
                `plan_name` VARCHAR(255) NULL,
                `amount` DOUBLE NOT NULL DEFAULT 0,
                `currency` VARCHAR(3) NULL,
                `state` VARCHAR(32) NOT NULL,
                `starts_at` DATETIME(3) NOT NULL,
                `ends_at` DATETIME(3) NOT NULL,
                `ended_at` DATETIME(3) NULL,
                `end_reason` VARCHAR(32) NULL,
                `last_order_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.membership_subscription.customer_id` (`customer_id`),
                KEY `idx.membership_subscription.state` (`state`),
                KEY `idx.membership_subscription.ends_at` (`ends_at`),
                KEY `idx.membership_subscription.loyalty_tier_id` (`loyalty_tier_id`),
                KEY `idx.membership_subscription.membership_plan_id` (`membership_plan_id`),
                KEY `idx.membership_subscription.last_order_id` (`last_order_id`),
                CONSTRAINT `fk.membership_subscription.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_subscription.membership_plan_id` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_subscription.loyalty_tier_id` FOREIGN KEY (`loyalty_tier_id`) REFERENCES `loyalty_tier` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_subscription.last_order_id` FOREIGN KEY (`last_order_id`) REFERENCES `membership_order` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
