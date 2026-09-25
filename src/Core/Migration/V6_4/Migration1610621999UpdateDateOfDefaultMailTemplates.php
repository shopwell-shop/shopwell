<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1610621999UpdateDateOfDefaultMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1610621999;
    }

    public function update(Connection $connection): void
    {
        // update DELIVERY_STATE_SHIPPED_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY, $connection);

        // update DELIVERY_STATE_RETURNED_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY, $connection);

        // update DELIVERY_STATE_RETURNED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED, $connection);

        // update DELIVERY_STATE_CANCELLED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED, $connection);

        // update DELIVERY_STATE_SHIPPED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED, $connection);

        // update ORDER_STATE_OPEN
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN, $connection);

        // update ORDER_STATE_IN_PROGRESS
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS, $connection);

        // update ORDER_STATE_COMPLETED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED, $connection);

        // update ORDER_STATE_CANCELLED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED, $connection);

        // update TRANSACTION_STATE_REFUNDED_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY, $connection);

        // update TRANSACTION_STATE_REMINDED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED, $connection);

        // update TRANSACTION_STATE_OPEN
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN, $connection);

        // update TRANSACTION_STATE_PAID | updated in other place

        // update TRANSACTION_STATE_CANCELLED | updated in other place

        // update TRANSACTION_STATE_REFUNDED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED, $connection);

        // update TRANSACTION_STATE_PAID_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateMailTemplatesByType(string $type, Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            $type,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $type . '/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $type . '/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $type . '/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $type . '/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }
}
