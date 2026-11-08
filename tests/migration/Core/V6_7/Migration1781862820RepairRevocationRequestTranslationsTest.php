<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1781862820RepairRevocationRequestTranslations;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1781862820RepairRevocationRequestTranslations::class)]
class Migration1781862820RepairRevocationRequestTranslationsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1781862820, (new Migration1781862820RepairRevocationRequestTranslations())->getCreationTimestamp());
    }

    public function testUpdateRepairsRegionalAndForeignRevocationTranslations(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->deleteRevocationMailTemplates();

            $zhHkLanguageByteId = $this->createLanguage('zh-HK');
            $enUsLanguageByteId = $this->createLanguage('en-US');
            $frChLanguageByteId = $this->createLanguage('fr-CH', 'fr-LI');

            $migration = new Migration1781862820RepairRevocationRequestTranslations();
            $migration->update($this->connection);
            $migration->update($this->connection);

            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
                $zhHkLanguageByteId,
                '已收到撤销申请'
            );
            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
                $enUsLanguageByteId,
                'Revocation request received'
            );
            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
                $frChLanguageByteId,
                'Revocation request received'
            );

            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
                $zhHkLanguageByteId,
                '撤销申请已发送'
            );
            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
                $frChLanguageByteId,
                'Revocation request sent'
            );
        } finally {
            $this->connection->rollBack();
        }
    }

    private function deleteRevocationMailTemplates(): void
    {
        foreach ([MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT, MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER] as $technicalName) {
            $mailTemplateTypeByteId = $this->connection->fetchOne(
                'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName LIMIT 1',
                ['technicalName' => $technicalName]
            );

            if (!\is_string($mailTemplateTypeByteId)) {
                continue;
            }

            $mailTemplateByteIds = $this->connection->fetchFirstColumn(
                'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
                ['mailTemplateTypeId' => $mailTemplateTypeByteId]
            );

            foreach ($mailTemplateByteIds as $mailTemplateByteId) {
                if (!\is_string($mailTemplateByteId)) {
                    continue;
                }

                $this->connection->delete('mail_template_translation', ['mail_template_id' => $mailTemplateByteId]);
                $this->connection->delete('mail_template', ['id' => $mailTemplateByteId]);
            }

            $this->connection->delete('mail_template_type_translation', ['mail_template_type_id' => $mailTemplateTypeByteId]);
            $this->connection->delete('mail_template_type', ['id' => $mailTemplateTypeByteId]);
        }
    }

    private function assertMailTemplateTranslation(string $technicalName, string $languageByteId, string $expectedSubject): void
    {
        $subject = $this->connection->fetchOne(
            <<<'SQL'
SELECT `mail_template_translation`.`subject`
FROM `mail_template_translation`
INNER JOIN `mail_template`
    ON `mail_template`.`id` = `mail_template_translation`.`mail_template_id`
INNER JOIN `mail_template_type`
    ON `mail_template_type`.`id` = `mail_template`.`mail_template_type_id`
WHERE `mail_template_type`.`technical_name` = :technicalName
    AND `mail_template`.`system_default` = 1
    AND `mail_template_translation`.`language_id` = :languageId
LIMIT 1
SQL,
            [
                'languageId' => $languageByteId,
                'technicalName' => $technicalName,
            ]
        );

        static::assertSame($expectedSubject, $subject);
    }

    private function createLanguage(string $localeCode, ?string $translationCode = null): string
    {
        $localeByteId = $this->getOrCreateLocale($localeCode);
        $translationCodeByteId = $translationCode === null ? $localeByteId : $this->getOrCreateLocale($translationCode);
        $languageByteId = Uuid::randomBytes();

        $this->connection->insert('language', [
            'id' => $languageByteId,
            'name' => $localeCode,
            'locale_id' => $localeByteId,
            'translation_code_id' => $translationCodeByteId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $languageByteId;
    }

    private function getOrCreateLocale(string $localeCode): string
    {
        $localeByteId = $this->connection->fetchOne(
            'SELECT `id` FROM `locale` WHERE `code` = :code LIMIT 1',
            ['code' => $localeCode]
        );

        if (\is_string($localeByteId)) {
            return $localeByteId;
        }

        $localeByteId = Uuid::randomBytes();

        $this->connection->insert('locale', [
            'id' => $localeByteId,
            'code' => $localeCode,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $localeByteId;
    }
}
