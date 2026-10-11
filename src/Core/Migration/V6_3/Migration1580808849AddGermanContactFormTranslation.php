<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1580808849AddGermanContactFormTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580808849;
    }

    public function update(Connection $connection): void
    {
        $zhCnLangId = $this->getZhCnLanguageId($connection);

        if ($zhCnLangId === null) {
            return;
        }

        $contactTemplateId = $this->getContactMailTemplateId($connection);

        if (!$contactTemplateId) {
            return;
        }

        $zhCnTranslation = $connection->fetchOne(
            'SELECT `mail_template_id` FROM `mail_template_translation` WHERE `mail_template_id` = :mail_template_id AND `language_id` = :language_id LIMIT 1',
            [
                'mail_template_id' => $contactTemplateId,
                'language_id' => $zhCnLangId,
            ]
        );

        if ($zhCnTranslation) {
            return;
        }

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $contactTemplateId,
                'language_id' => $zhCnLangId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => '收到联系表单咨询 - {{ salesChannel.name }}',
                'description' => '收到联系表单咨询',
                'content_html' => $this->getContactFormHtmlTemplateZhCn(),
                'content_plain' => $this->getContactFormPlainTemplateZhCn(),
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function getZhCnLanguageId(Connection $connection): ?string
    {
        $result = $connection->fetchOne('
            SELECT `language`.`id` FROM `language`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `locale`.`code` = \'zh-CN\'
        ');

        return $result === false ? null : (string) $result;
    }

    private function getContactMailTemplateId(Connection $connection): ?string
    {
        $sql = <<<'SQL'
    SELECT `mail_template`.`id`
    FROM `mail_template` LEFT JOIN `mail_template_type` ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
    WHERE `mail_template_type`.`technical_name` = :technical_name
    AND `system_default` = 1
SQL;

        $result = $connection->executeQuery(
            $sql,
            ['technical_name' => MailTemplateTypes::MAILTYPE_CONTACT_FORM]
        )->fetchOne();

        return $result === false ? null : (string) $result;
    }

    private function getContactFormHtmlTemplateZhCn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        {{ contactFormData.name }} 通过联系表单给您发送了以下消息。<br/>
        <br/>
        联系邮箱：{{ contactFormData.email }}<br/>
        <br>
        电话号码：{{ contactFormData.phone }}<br/>
        <br/>
        主题：{{ contactFormData.subject }}<br/>
        <br/>
        消息：{{ contactFormData.comment }}<br/>
    </p>
</div>';
    }

    private function getContactFormPlainTemplateZhCn(): string
    {
        return '{{ contactFormData.name }} 通过联系表单给您发送了以下消息。

联系邮箱：{{ contactFormData.email }}

电话号码：{{ contactFormData.phone }}

主题：{{ contactFormData.subject }}

消息：{{ contactFormData.comment }}';
    }
}
