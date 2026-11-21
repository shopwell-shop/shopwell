<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\V6_7\Migration1793904418UpdateMailTemplatesForMultipleShipments;
use Shopwell\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1793904418UpdateMailTemplatesForMultipleShipments::class)]
class Migration1793904418UpdateMailTemplatesForMultipleShipmentsTest extends MailTemplateMigrationTestCase
{
    private const MAIL_TEMPLATE_TYPES = [
        MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
    ];

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793904418, (new Migration1793904418UpdateMailTemplatesForMultipleShipments())->getCreationTimestamp());
    }

    public function testMigrationUpdatesUnmodifiedMailTemplates(): void
    {
        $this->prepareDefaultMailTemplates(null);

        $migration = new Migration1793904418UpdateMailTemplatesForMultipleShipments();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $expected = new MailUpdate($mailTemplateType);
            $expected->loadByDirectoryName($mailTemplateType);

            $translations = $this->getMailTemplateTranslations($mailTemplateType)->translations;

            static::assertSame($expected->getEnPlain(), $translations->getEnPlain(), $mailTemplateType . ': en plain');
            static::assertSame($expected->getEnHtml(), $translations->getEnHtml(), $mailTemplateType . ': en html');
            static::assertSame($expected->getZhPlain(), $translations->getZhPlain(), $mailTemplateType . ': zh plain');
            static::assertSame($expected->getZhHtml(), $translations->getZhHtml(), $mailTemplateType . ': zh html');
        }
    }

    public function testMigrationKeepsMailTemplatesModifiedByMerchants(): void
    {
        $this->prepareDefaultMailTemplates('2026-11-05 12:00:00.000');

        (new Migration1793904418UpdateMailTemplatesForMultipleShipments())->update($this->connection);

        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $translations = $this->getMailTemplateTranslations($mailTemplateType)->translations;

            static::assertSame('BEFORE-plain', $translations->getEnPlain(), $mailTemplateType . ': en plain');
            static::assertSame('BEFORE-html', $translations->getEnHtml(), $mailTemplateType . ': en html');
            static::assertSame('BEFORE-plain', $translations->getZhPlain(), $mailTemplateType . ': zh plain');
            static::assertSame('BEFORE-html', $translations->getZhHtml(), $mailTemplateType . ': zh html');
        }
    }

    private function prepareDefaultMailTemplates(?string $translationUpdatedAt): void
    {
        $this->connection->executeStatement(
            '
            UPDATE `mail_template` AS `template`
            INNER JOIN `mail_template_type` AS `type`
                ON `template`.`mail_template_type_id` = `type`.`id`
            SET `template`.`updated_at` = NULL
            WHERE `type`.`technical_name` IN (:technicalNames)
            ',
            ['technicalNames' => self::MAIL_TEMPLATE_TYPES],
            ['technicalNames' => ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            '
            UPDATE `mail_template_translation` AS `translation`
            INNER JOIN `mail_template` AS `template`
                ON `translation`.`mail_template_id` = `template`.`id`
            INNER JOIN `mail_template_type` AS `type`
                ON `template`.`mail_template_type_id` = `type`.`id`
            SET `translation`.`updated_at` = :updatedAt,
                `translation`.`content_html` = :contentHtml,
                `translation`.`content_plain` = :contentPlain
            WHERE `type`.`technical_name` IN (:technicalNames)
            ',
            [
                'updatedAt' => $translationUpdatedAt,
                'contentHtml' => 'BEFORE-html',
                'contentPlain' => 'BEFORE-plain',
                'technicalNames' => self::MAIL_TEMPLATE_TYPES,
            ],
            ['technicalNames' => ArrayParameterType::STRING],
        );
    }
}
