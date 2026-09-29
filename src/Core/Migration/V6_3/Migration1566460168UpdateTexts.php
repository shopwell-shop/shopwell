<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1566460168UpdateTexts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1566460168;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->updateInvoice($connection);
        $this->updateDirectDebit($connection);
        $this->updateCashOnDelivery($connection);
    }

    private function updateInvoice(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Payment by invoice. Shopwell provides automatic invoicing for all customers on orders after the first. This is to avoid defaults on payment.\'
            WHERE `description` = \'Payment by invoice. Shopwell provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.\'
            AND `name` = \'Invoice\';
        ');

        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'您可方便地使用发票付款。Shopwell 也可设置从第 2 笔订单起开放发票付款，以降低坏账风险。\'
            WHERE `description` = \'您可方便地使用发票付款。Shopwell 也可设置从第 2 单起开放发票付款，以降低坏账风险。\'
            AND `name` = \'发票\';
        ');
    }

    private function updateCashOnDelivery(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Payment upon receipt of goods.\'
            WHERE `description` = \'Pay when you get the order\'
            AND `name` = \'Cash on delivery\';
        ');

        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'收到商品时付款。\'
            WHERE `description` = \'\'
            AND `name` = \'货到付款\';
        ');
    }

    private function updateDirectDebit(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Pre-authorized payment, funds are withdrawn directly from the debited account.\'
            WHERE `description` =\'Additional text\'
            AND `name` = \'Direct Debit\';
        ');

        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'预先授权的付款协议，款项将直接从您的账户扣划。\'
            WHERE `description` = \'补充说明\'
            AND `name` = \'银行代扣\';
        ');
    }
}
