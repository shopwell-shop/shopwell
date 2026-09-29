<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1570622696CustomerPasswordRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570622696;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `customer_recovery` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `hash` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.customer_recovery.customer_id` UNIQUE (`customer_id`),
                CONSTRAINT `fk.customer_recovery.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($query);

        $mailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
        $this->registerEventAction($connection, $mailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $zhCnLangId = $this->getLanguageIdByLocale($connection, 'zh-CN');

        $connection->insert('mail_template_type', [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technical_name' => 'customer.recovery.request',
            'available_entities' => json_encode(['customerRecovery' => 'customer_recovery']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $zhCnLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $defaultLangId,
                'name' => 'Customer password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Customer password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($zhCnLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $zhCnLangId,
                'name' => '客户密码找回',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $zhCnLangId = $this->getLanguageIdByLocale($connection, 'zh-CN');

        $connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'system_default' => true,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $zhCnLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $defaultLangId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password recovery',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password recovery',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($zhCnLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $this->getLanguageIdByLocale($connection, 'zh-CN'),
                'sender_name' => '{{ shopName }}',
                'subject' => '密码找回',
                'description' => '',
                'content_html' => $this->getContentHtmlZhCn(),
                'content_plain' => $this->getContentPlainZhCn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $this->addTemplateToSalesChannels($connection, $mailTemplateTypeId, $mailTemplateId);
    }

    private function addTemplateToSalesChannels(Connection $connection, string $mailTemplateTypeId, string $mailTemplateId): void
    {
        $salesChannels = $connection->fetchAllAssociative('SELECT `id` FROM `sales_channel` ');

        foreach ($salesChannels as $salesChannel) {
            $mailTemplateSalesChannel = [
                'id' => Uuid::randomBytes(),
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'sales_channel_id' => $salesChannel['id'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];

            $connection->insert('mail_template_sales_channel', $mailTemplateSalesChannel);
        }
    }

    private function registerEventAction(Connection $connection, string $mailTemplateTypeId): void
    {
        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'event_name' => 'customer.recovery.request',
            'action_name' => SendMailAction::ACTION_NAME,
            'config' => json_encode(['mail_template_type_id' => $mailTemplateTypeId], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hello {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},<br/>
        <br/>
        You have requested a new password for your {{ shopName }} account.
        Click on the following link to reset your password:<br/>
        <br/>
        <a href="{{ resetUrl }}">{{ resetUrl }}</a><br/>
        <br/>
        This link is valid for the next 2 hours.
        If you don't want to reset your password, ignore this email and no changes will be made.<br/>
        <br/>
        Yours sincerely
        Your {{ shopName }} team
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        Hello {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},

        You have requested a new password for your {{ shopName }} account.
        Click on the following link to reset your password:

        {{ resetUrl }}

        This link is valid for the next 2 hours.
        If you don't want to reset your password, ignore this email and no changes will be made.

        Yours sincerely
        Your {{ shopName }}-Team
MAIL;
    }

    private function getContentHtmlZhCn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        您好 {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},<br/>
        <br/>
        您申请为 {{ shopName }} 账户重置密码。
        请点击下方链接重置密码：<br/>
        <br/>
        <a href="{{ resetUrl }}">{{ resetUrl }}</a><br/>
        <br/>
        该链接仅在 2 小时内有效。
        若您不想重置密码，请忽略本邮件，密码不会发生任何变更。<br/>
        <br/>
        此致
        {{ shopName }} 团队
    </p>
</div>
MAIL;
    }

    private function getContentPlainZhCn(): string
    {
        return <<<MAIL
        您好 {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},

        您申请为 {{ shopName }} 账户重置密码。
        请点击下方链接重置密码：

        {{ resetUrl }}

        该链接仅在 2 小时内有效。
        若您不想重置密码，请忽略本邮件，密码不会发生任何变更。

        此致
        {{ shopName }} 团队
MAIL;
    }
}
