<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\MailSubjectUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1788180753FixPaymentReminderMailSubject extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1788180753;
    }

    public function update(Connection $connection): void
    {
        $this->updateMailSubject(
            new MailSubjectUpdate(
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
                'Payment reminder for your order with {{ salesChannel.translated.name }}',
                '您在 {{ salesChannel.translated.name }} 的订单付款提醒',
            ),
            $connection
        );
    }
}
