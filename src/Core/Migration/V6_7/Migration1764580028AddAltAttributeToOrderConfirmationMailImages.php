<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

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
class Migration1764580028AddAltAttributeToOrderConfirmationMailImages extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1764580028;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $orderConfirmUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $orderConfirmUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'));
        $orderConfirmUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'));
        $orderConfirmUpdate->setZhPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/zh-plain.html.twig'));
        $orderConfirmUpdate->setZhHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/zh-html.html.twig'));
        $this->updateMail($orderConfirmUpdate, $connection);

        $paidUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID);
        $paidUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'));
        $paidUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'));
        $paidUpdate->setZhPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/zh-plain.html.twig'));
        $paidUpdate->setZhHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/zh-html.html.twig'));
        $this->updateMail($paidUpdate, $connection);

        $cancelledUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED);
        $cancelledUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'));
        $cancelledUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'));
        $cancelledUpdate->setZhPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/zh-plain.html.twig'));
        $cancelledUpdate->setZhHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/zh-html.html.twig'));
        $this->updateMail($cancelledUpdate, $connection);
    }
}
