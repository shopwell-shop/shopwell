<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1788531858FixGaranLabelInOrderConfirmationMail extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1788531858;
    }

    public function update(Connection $connection): void
    {
        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $update->loadByDirectoryName('order_confirmation_mail');

        $this->updateMail($update, $connection);
    }
}
