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
class Migration1562240231UserPasswordRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562240231;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
        $this->registerEventAction($connection, $mailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
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
            'technical_name' => 'user.recovery.request',
            'available_entities' => json_encode(['userRecovery' => 'user_recovery']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $zhCnLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $defaultLangId,
                'name' => 'User password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'User password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($zhCnLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $zhCnLangId,
                'name' => '用户密码找回',
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
                'sender_name' => 'Shopwell Administration',
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
                'sender_name' => 'Shopwell Administration',
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
                'language_id' => $zhCnLangId,
                'sender_name' => 'Shopwell Administration',
                'subject' => '密码找回',
                'description' => '',
                'content_html' => $this->getContentHtmlZhCn(),
                'content_plain' => $this->getContentPlainZhCn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function registerEventAction(Connection $connection, string $mailTemplateTypeId): void
    {
        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'event_name' => 'user.recovery.request',
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
        Dear {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},<br/>
        <br/>
        there has been a request to reset your password.
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ resetUrl }}">Reset passwort</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        Dear {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},

        there has been a request to reset your password.
        Please confirm the link below to specify a new password.

        Reset password: {{ resetUrl }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
MAIL;
    }

    private function getContentHtmlZhCn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        您好 {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},<br/>
        <br/>
        我们收到了重置您密码的请求。
        请通过下方链接设置新密码。<br/>
        <br/>
        <a href="{{ resetUrl }}">重置密码</a><br/>
        <br/>
        该链接仅在 2 小时内有效，过期后需重新申请重置密码。
        若您不想重置密码，请忽略本邮件，密码不会发生任何变更。
    </p>
</div>
MAIL;
    }

    private function getContentPlainZhCn(): string
    {
        return <<<MAIL
        您好 {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},

        我们收到了重置您密码的请求。
        请通过下方链接设置新密码。

        重置密码：{{ resetUrl }}

        该链接仅在 2 小时内有效，过期后需重新申请重置密码。
        若您不想重置密码，请忽略本邮件，密码不会发生任何变更。
MAIL;
    }
}
