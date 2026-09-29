<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1788180753FixPaymentReminderMailSubject;
use Shopwell\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1788180753FixPaymentReminderMailSubject::class)]
class Migration1788180753FixPaymentReminderMailSubjectTest extends MailTemplateMigrationTestCase
{
    public function testMigrationUpdatesPaymentReminderMailSubject(): void
    {
        $this->prepareDefaultMailTemplateSubject('New document for your order', '您的订单有新文档');

        $migration = new Migration1788180753FixPaymentReminderMailSubject();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $subjects = $this->getPaymentReminderMailTemplateSubjects();

        static::assertSame('Payment reminder for your order with {{ salesChannel.translated.name }}', $subjects['en']);
        static::assertSame('您在 {{ salesChannel.translated.name }} 的订单付款提醒', $subjects['zh']);
    }

    public function testMigrationDoesNotOverwriteCustomizedPaymentReminderMailSubject(): void
    {
        $this->prepareCustomizedMailTemplateSubject('Custom EN subject', '自定义中文主题');

        (new Migration1788180753FixPaymentReminderMailSubject())->update($this->connection);

        $subjects = $this->getPaymentReminderMailTemplateSubjects();

        static::assertSame('Custom EN subject', $subjects['en']);
        static::assertSame('自定义中文主题', $subjects['zh']);
    }

    private function prepareDefaultMailTemplateSubject(string $enSubject, string $zhSubject): void
    {
        $this->prepareMailTemplateSubject($enSubject, $zhSubject, null, null);
    }

    private function prepareCustomizedMailTemplateSubject(string $enSubject, string $zhSubject): void
    {
        $updatedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->prepareMailTemplateSubject($enSubject, $zhSubject, $updatedAt, $updatedAt);
    }

    private function prepareMailTemplateSubject(
        string $enSubject,
        string $zhSubject,
        ?string $templateUpdatedAt,
        ?string $translationUpdatedAt
    ): void {
        $mailTemplateTypeId = $this->getMailTemplateTypeId(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $this->connection->executeStatement(
            'UPDATE `mail_template` SET `updated_at` = :updatedAt WHERE `id` = :id',
            [
                'id' => $mailTemplateId,
                'updatedAt' => $templateUpdatedAt,
            ],
        );

        $this->connection->executeStatement(
            '
            UPDATE `mail_template_translation`
            SET `subject` = CASE
                    WHEN `language_id` = :enLanguageId THEN :enSubject
                    WHEN `language_id` = :zhLanguageId THEN :zhSubject
                    ELSE `subject`
                END,
                `updated_at` = :updatedAt
            WHERE `mail_template_id` = :mailTemplateId
            ',
            [
                'mailTemplateId' => $mailTemplateId,
                'enLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'enSubject' => $enSubject,
                'zhLanguageId' => Uuid::fromHexToBytes($this->getLanguageId('zh-CN')),
                'zhSubject' => $zhSubject,
                'updatedAt' => $translationUpdatedAt,
            ],
        );
    }

    /**
     * @return array{en: string, zh: string}
     */
    private function getPaymentReminderMailTemplateSubjects(): array
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $subjects = $this->connection->fetchAllKeyValue(
            '
            SELECT LOWER(HEX(`language_id`)), `subject`
            FROM `mail_template_translation`
            WHERE `mail_template_id` = :mailTemplateId
                AND `language_id` IN (:enLanguageId, :zhLanguageId)
            ',
            [
                'mailTemplateId' => $mailTemplateId,
                'enLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'zhLanguageId' => Uuid::fromHexToBytes($this->getLanguageId('zh-CN')),
            ],
        );

        return [
            'en' => $subjects[Defaults::LANGUAGE_SYSTEM],
            'zh' => $subjects[$this->getLanguageId('zh-CN')],
        ];
    }

    private function getLanguageId(string $localeCode): string
    {
        $languageId = $this->connection->fetchOne(
            '
            SELECT LOWER(HEX(`language`.`id`))
            FROM `language`
            INNER JOIN `locale`
                ON `language`.`locale_id` = `locale`.`id`
                    AND `locale`.`code` = :localeCode
            ',
            ['localeCode' => $localeCode],
        );

        static::assertIsString($languageId);

        return $languageId;
    }
}
