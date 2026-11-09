<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopwell\Core\Migration\Structs\MailTemplateTypeCreateStruct;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1781862820RepairRevocationRequestTranslations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1781862820;
    }

    public function update(Connection $connection): void
    {
        $this->repairRevocationRequestMailTemplates($connection);
    }

    private function repairRevocationRequestMailTemplates(Connection $connection): void
    {
        $merchantType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
            'Revocation request received',
            '已收到撤销申请',
        );

        $merchantTemplate = new MailTemplateCreateStruct(
            Migration1768545319RevocationRequestMailTemplate::MERCHANT_DIRECTORY,
            'Revocation request received',
            '已收到撤销申请',
            'Received revocation request from customer',
            '已收到客户的撤销申请',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->repairMailTemplate($connection, $merchantType, $merchantTemplate);

        $customerType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
            'Revocation request requested',
            '撤销申请已提交',
        );

        $customerTemplate = new MailTemplateCreateStruct(
            Migration1768545319RevocationRequestMailTemplate::CUSTOMER_DIRECTORY,
            'Revocation request sent',
            '撤销申请已发送',
            'Confirmation receipt of customers revocation request',
            '客户撤销申请的接收确认',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->repairMailTemplate($connection, $customerType, $customerTemplate);
    }

    private function repairMailTemplate(Connection $connection, MailTemplateTypeCreateStruct $mailTemplateType, MailTemplateCreateStruct $mailTemplate): void
    {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $mailTemplateType->getTechnicalName());
        if ($mailTemplateTypeByteId === null) {
            $mailTemplateTypeByteId = Uuid::randomBytes();

            $connection->insert('mail_template_type', [
                'id' => $mailTemplateTypeByteId,
                'technical_name' => $mailTemplateType->getTechnicalName(),
                'available_entities' => \json_encode($mailTemplateType->getAvailableEntities(), \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $mailTemplateByteId = $this->getUnmodifiedSystemDefaultMailTemplateId($connection, $mailTemplateTypeByteId);
        if ($mailTemplateByteId === null) {
            if ($this->hasSystemDefaultMailTemplate($connection, $mailTemplateTypeByteId)) {
                return;
            }

            $mailTemplateByteId = Uuid::randomBytes();

            $connection->insert('mail_template', [
                'id' => $mailTemplateByteId,
                'mail_template_type_id' => $mailTemplateTypeByteId,
                'system_default' => $mailTemplate->isSystemDefault(),
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        foreach ($this->getLanguageIdsWithZhPrefix($connection) as $zhCnLanguageByteId) {
            $this->repairMailTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $zhCnLanguageByteId, $mailTemplateType->getZhName());
            $this->repairMailTemplateTranslation($connection, $mailTemplateByteId, $zhCnLanguageByteId, [
                'sender_name' => $mailTemplate->getZhSenderName(),
                'subject' => $mailTemplate->getZhSubject(),
                'description' => $mailTemplate->getZhDescription(),
                'content_html' => $mailTemplate->getZhHtml(),
                'content_plain' => $mailTemplate->getZhPlain(),
            ]);
        }

        foreach ($this->getLanguageIdsWithoutZhPrefix($connection) as $enLanguageByteId) {
            $this->repairMailTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $enLanguageByteId, $mailTemplateType->getEnName());
            $this->repairMailTemplateTranslation($connection, $mailTemplateByteId, $enLanguageByteId, [
                'sender_name' => $mailTemplate->getEnSenderName(),
                'subject' => $mailTemplate->getEnSubject(),
                'description' => $mailTemplate->getEnDescription(),
                'content_html' => $mailTemplate->getEnHtml(),
                'content_plain' => $mailTemplate->getEnPlain(),
            ]);
        }
    }

    private function getMailTemplateTypeId(Connection $connection, string $technicalName): ?string
    {
        $mailTemplateTypeByteId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName LIMIT 1',
            ['technicalName' => $technicalName],
        );

        if (!\is_string($mailTemplateTypeByteId)) {
            return null;
        }

        return $mailTemplateTypeByteId;
    }

    private function getUnmodifiedSystemDefaultMailTemplateId(Connection $connection, string $mailTemplateTypeByteId): ?string
    {
        $mailTemplateByteId = $connection->fetchOne(
            <<<'SQL'
SELECT `id`
FROM `mail_template`
WHERE `mail_template_type_id` = :mailTemplateTypeId
    AND `system_default` = 1
    AND `updated_at` IS NULL
ORDER BY `created_at` ASC, `id` ASC
LIMIT 1
SQL,
            ['mailTemplateTypeId' => $mailTemplateTypeByteId],
        );

        if (!\is_string($mailTemplateByteId)) {
            return null;
        }

        return $mailTemplateByteId;
    }

    private function hasSystemDefaultMailTemplate(Connection $connection, string $mailTemplateTypeByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `system_default` = 1 LIMIT 1',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId],
        );
    }

    private function repairMailTemplateTypeTranslation(Connection $connection, string $mailTemplateTypeByteId, string $languageByteId, string $name): void
    {
        $translation = $connection->fetchAssociative(
            'SELECT `updated_at` FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `language_id` = :languageId LIMIT 1',
            ['languageId' => $languageByteId, 'mailTemplateTypeId' => $mailTemplateTypeByteId],
        );

        if (\is_array($translation)) {
            if ($translation['updated_at'] !== null) {
                return;
            }

            $connection->update(
                'mail_template_type_translation',
                ['name' => $name],
                ['language_id' => $languageByteId, 'mail_template_type_id' => $mailTemplateTypeByteId],
            );

            return;
        }

        $connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => $mailTemplateTypeByteId,
            'language_id' => $languageByteId,
            'name' => $name,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array{sender_name: string, subject: string, description: string, content_html: string, content_plain: string} $translationData
     */
    private function repairMailTemplateTranslation(Connection $connection, string $mailTemplateByteId, string $languageByteId, array $translationData): void
    {
        $translation = $connection->fetchAssociative(
            'SELECT `updated_at` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND `language_id` = :languageId LIMIT 1',
            ['languageId' => $languageByteId, 'mailTemplateId' => $mailTemplateByteId],
        );

        if (\is_array($translation)) {
            if ($translation['updated_at'] !== null) {
                return;
            }

            $connection->update(
                'mail_template_translation',
                $translationData,
                ['language_id' => $languageByteId, 'mail_template_id' => $mailTemplateByteId],
            );

            return;
        }

        $connection->insert('mail_template_translation', [
            'mail_template_id' => $mailTemplateByteId,
            'language_id' => $languageByteId,
            ...$translationData,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return list<string>
     */
    private function getLanguageIdsWithZhPrefix(Connection $connection): array
    {
        /** @var list<string> $languageIds */
        $languageIds = $connection->fetchFirstColumn(
            <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE LOWER(`locale`.`code`) LIKE 'zh-%'
ORDER BY `language`.`created_at` ASC, `language`.`id` ASC
SQL
        );

        return $languageIds;
    }

    /**
     * @return list<string>
     */
    private function getLanguageIdsWithoutZhPrefix(Connection $connection): array
    {
        /** @var list<string> $languageIds */
        $languageIds = $connection->fetchFirstColumn(
            <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE LOWER(`locale`.`code`) NOT LIKE 'zh-%'
ORDER BY `language`.`created_at` ASC, `language`.`id` ASC
SQL
        );

        return $languageIds;
    }
}
