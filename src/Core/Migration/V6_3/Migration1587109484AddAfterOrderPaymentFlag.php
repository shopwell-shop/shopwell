<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1587109484AddAfterOrderPaymentFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587109484;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE payment_method
            ADD COLUMN `after_order_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`'
        );

        $connection->executeStatement(
            'UPDATE `payment_method`
            SET `after_order_enabled` = 1 WHERE `handler_identifier` IN (
                "Shopwell\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\DebitPayment",
                "Shopwell\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\CashPayment",
                "Shopwell\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\PrePayment",
                "Shopwell\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\InvoicePayment"
            )'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
