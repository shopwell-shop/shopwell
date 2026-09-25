<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

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
class Migration1692254551FixMailTranslation extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1692254551;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $updateAuthorizedMail = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-html.html.twig'),
        );
        $this->updateMail($updateAuthorizedMail, $connection);

        $updateChargebackMail = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig'),
        );
        $this->updateMail($updateChargebackMail, $connection);

        $updateUnconfirmedMail = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig'),
        );
        $this->updateMail($updateUnconfirmedMail, $connection);
    }
}
