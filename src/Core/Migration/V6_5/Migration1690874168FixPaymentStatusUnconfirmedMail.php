<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1690874168FixPaymentStatusUnconfirmedMail extends MigrationStep
{
    use UpdateMailTrait;

    private const ZH_CN_LANGUAGE_NAME = '简体中文';

    public function getCreationTimestamp(): int
    {
        return 1690874168;
    }

    public function update(Connection $connection): void
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => Migration1688106315AddMissingTransactionMailTemplates::UNCONFIRMED_TYPE]);
        $templateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $templateTypeId]);

        $languageId = $connection->fetchOne(
            'SELECT id FROM `language` WHERE `name` = :name',
            ['name' => self::ZH_CN_LANGUAGE_NAME]
        );

        if (!\is_string($languageId)) {
            return;
        }

        $this->updateMailTemplateTranslation($connection, $templateId, $languageId);
        $this->updateMailTemplateTypeTranslation($connection, $templateTypeId, $languageId);
    }

    private function updateMailTemplateTranslation(Connection $connection, string $templateId, string $languageId): void
    {
        $connection->update(
            'mail_template_translation',
            ['subject' => '您在 {{ salesChannel.name }} 的订单未确认'],
            ['mail_template_id' => $templateId, 'language_id' => $languageId],
        );
    }

    private function updateMailTemplateTypeTranslation(Connection $connection, string $templateTypeId, string $languageId): void
    {
        $connection->update(
            'mail_template_type_translation',
            ['name' => '进入支付状态：未确认'],
            ['mail_template_type_id' => $templateTypeId, 'language_id' => $languageId],
        );
    }
}
