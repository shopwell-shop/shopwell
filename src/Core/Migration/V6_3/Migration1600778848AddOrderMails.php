<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1600778848AddOrderMails extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1600778848;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            'order_confirmation_mail',
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig')
        );

        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            'order_transaction.state.cancelled',
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig')
        );

        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            'order_transaction.state.paid',
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
