<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;

/**
 * Creates the tables and the state machine of a return in their final shape.
 *
 * The model is the one shopify and the commercial returns management of shopware use: the return is
 * the goods, the money is a refund of the payment gateway. There is therefore no amount, no refund
 * state and no gateway reference in these tables beyond the number of the refund that paid the
 * return out - a return that has not been paid out yet simply has none.
 *
 * `order_return` carries the request and its decision, `order_return_line_item` the positions with
 * the quantity that really goes back, `order_return_message` the conversation and
 * `order_return_reason` the maintained list of reasons. The list is seeded with four neutral entries
 * a shop can delete or replace - which reasons fit depends on what is sold, so they are data and not
 * code.
 *
 * The state machine is imported first, because `order_return.state_id` references it.
 *
 * @internal
 */
#[Package('after-sales')]
class Migration1792700100CreateOrderReturnTables extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1792700100;
    }

    public function update(Connection $connection): void
    {
        $this->importStateMachine($connection);
        $this->createReasonTable($connection);
        $this->createReturnTable($connection);
        $this->createLineItemTable($connection);
        $this->createMessageTable($connection);
        $this->seedReasons($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function importStateMachine(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                OrderReturnStates::STATE_MACHINE,
                '退货单状态',
                'Order return state',
                [
                    StateMachineMigration::state(OrderReturnStates::STATE_REQUESTED, '待审核', 'Requested'),
                    StateMachineMigration::state(OrderReturnStates::STATE_OPEN, '已同意待寄回', 'Open'),
                    StateMachineMigration::state(OrderReturnStates::STATE_RETURNING, '退货物流中', 'Returning'),
                    StateMachineMigration::state(OrderReturnStates::STATE_RECEIVED, '已收货待退款', 'Received'),
                    StateMachineMigration::state(OrderReturnStates::STATE_REFUNDING, '退款中', 'Refunding'),
                    StateMachineMigration::state(OrderReturnStates::STATE_CLOSED, '已完成', 'Closed'),
                    StateMachineMigration::state(OrderReturnStates::STATE_DECLINED, '已拒绝', 'Declined'),
                    StateMachineMigration::state(OrderReturnStates::STATE_CANCELED, '已取消', 'Canceled'),
                ],
                [
                    StateMachineMigration::transition(OrderReturnStates::ACTION_APPROVE, OrderReturnStates::STATE_REQUESTED, OrderReturnStates::STATE_OPEN),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_DECLINE, OrderReturnStates::STATE_REQUESTED, OrderReturnStates::STATE_DECLINED),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_CANCEL, OrderReturnStates::STATE_REQUESTED, OrderReturnStates::STATE_CANCELED),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_CANCEL, OrderReturnStates::STATE_OPEN, OrderReturnStates::STATE_CANCELED),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_SHIP, OrderReturnStates::STATE_OPEN, OrderReturnStates::STATE_RETURNING),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_RECEIVE, OrderReturnStates::STATE_RETURNING, OrderReturnStates::STATE_RECEIVED),
                    // a refund without goods is paid out straight from the approval, a return only once
                    // the goods are accepted
                    StateMachineMigration::transition(OrderReturnStates::ACTION_REFUND, OrderReturnStates::STATE_OPEN, OrderReturnStates::STATE_REFUNDING),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_REFUND, OrderReturnStates::STATE_RECEIVED, OrderReturnStates::STATE_REFUNDING),
                    StateMachineMigration::transition(OrderReturnStates::ACTION_FINISH, OrderReturnStates::STATE_REFUNDING, OrderReturnStates::STATE_CLOSED),
                ],
                OrderReturnStates::STATE_REQUESTED,
            ),
            $connection
        );
    }

    private function createReasonTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `order_return_reason` (
                `id` BINARY(16) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `position` INT NOT NULL DEFAULT 0,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.order_return_reason.active_position` (`active`, `position`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `order_return_reason_translation` (
                `order_return_reason_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`order_return_reason_id`, `language_id`),
                KEY `fk.order_return_reason_translation.language_id` (`language_id`),
                CONSTRAINT `fk.order_return_reason_translation.order_return_reason_id` FOREIGN KEY (`order_return_reason_id`) REFERENCES `order_return_reason` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return_reason_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The reference to the order spans id and version, exactly like the one of an order transaction:
     * an order is versioned, so a reference that named only the id would point into whatever version
     * happens to be the live one later.
     */
    private function createReturnTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `order_return` (
                `id` BINARY(16) NOT NULL,
                `return_number` VARCHAR(64) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `guest_email` VARCHAR(255) NULL,
                `guest_token_hash` VARCHAR(128) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `reason_id` BINARY(16) NULL,
                `reason_text` VARCHAR(1024) NULL,
                `customer_note` LONGTEXT NULL,
                `evidence` JSON NULL,
                `refund_amount` INT NOT NULL DEFAULT 0,
                `currency` VARCHAR(3) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `payment_refund_no` VARCHAR(64) NULL,
                `administrator_id` BINARY(16) NULL,
                `deadline_at` DATETIME(3) NULL,
                `returned_at` DATETIME(3) NULL,
                `received_at` DATETIME(3) NULL,
                `tracking_code` VARCHAR(128) NULL,
                `tracking_carrier` VARCHAR(64) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.order_return.return_number` (`return_number`),
                KEY `idx.order_return.order_id` (`order_id`, `order_version_id`),
                KEY `idx.order_return.customer_id` (`customer_id`),
                KEY `idx.order_return.state_id` (`state_id`),
                KEY `idx.order_return.deadline_at` (`deadline_at`),
                CONSTRAINT `fk.order_return.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return.reason_id` FOREIGN KEY (`reason_id`) REFERENCES `order_return_reason` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return.administrator_id` FOREIGN KEY (`administrator_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The line item of the order is versioned as well, so the position references it with both
     * columns. `restocked` and `accepted` are per position on purpose: a parcel may come back with one
     * item unusable and another one fine, and only the fine one goes back into the stock.
     */
    private function createLineItemTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `order_return_line_item` (
                `id` BINARY(16) NOT NULL,
                `order_return_id` BINARY(16) NOT NULL,
                `order_line_item_id` BINARY(16) NOT NULL,
                `order_line_item_version_id` BINARY(16) NOT NULL,
                `quantity` INT NOT NULL,
                `refund_amount` INT NOT NULL DEFAULT 0,
                `restocked` TINYINT(1) NOT NULL DEFAULT 0,
                `accepted` TINYINT(1) NOT NULL DEFAULT 1,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.order_return_line_item.order_return_id` (`order_return_id`),
                KEY `idx.order_return_line_item.order_line_item_id` (`order_line_item_id`, `order_line_item_version_id`),
                CONSTRAINT `fk.order_return_line_item.order_return_id` FOREIGN KEY (`order_return_id`) REFERENCES `order_return` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.order_return_line_item.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`) REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createMessageTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `order_return_message` (
                `id` BINARY(16) NOT NULL,
                `order_return_id` BINARY(16) NOT NULL,
                `author_role` VARCHAR(16) NOT NULL,
                `author_id` VARCHAR(64) NULL,
                `content` LONGTEXT NOT NULL,
                `attachments` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.order_return_message.order_return_id` (`order_return_id`, `created_at`),
                CONSTRAINT `fk.order_return_message.order_return_id` FOREIGN KEY (`order_return_id`) REFERENCES `order_return` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * Four neutral reasons so the storefront form is usable right after the installation. They name
     * no product category on purpose - a shop that sells something else deletes them and adds its own.
     */
    private function seedReasons(Connection $connection): void
    {
        $existing = (int) $connection->fetchOne('SELECT COUNT(*) FROM `order_return_reason`');

        if ($existing > 0) {
            return;
        }

        $reasons = [
            ['label' => '商品破损或存在质量问题', 'position' => 10],
            ['label' => '商品与描述不符', 'position' => 20],
            ['label' => '少件、错发或漏发', 'position' => 30],
            ['label' => '不想要了', 'position' => 40],
        ];

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($reasons as $reason) {
            $id = Uuid::randomBytes();

            $connection->insert('order_return_reason', [
                'id' => $id,
                'active' => true,
                'position' => $reason['position'],
                'created_at' => $now,
            ]);

            $connection->insert('order_return_reason_translation', [
                'order_return_reason_id' => $id,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'label' => $reason['label'],
                'created_at' => $now,
            ]);
        }
    }
}
