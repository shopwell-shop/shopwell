<?php

declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1711461580SetSystemDefaultForDefaultMailFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711461580;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $zhCnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/zh-html.twig');
        $zhCnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/zh-plain.twig');

        // Check if a template contains the Chinese default content.
        // The ID of the oldest one will be returned as this is the one, created during the installation.
        $mailFooterIdZhCnCheck = $connection->fetchOne(
            'SELECT id FROM mail_header_footer
             INNER JOIN mail_header_footer_translation
                ON mail_header_footer.id = mail_header_footer_translation.mail_header_footer_id
             WHERE mail_header_footer_translation.footer_html = :zhCnHtml
             AND mail_header_footer_translation.footer_plain = :zhCnPlain
             ORDER BY mail_header_footer_translation.created_at ASC
             LIMIT 1',
            [
                'zhCnHtml' => $zhCnHtml,
                'zhCnPlain' => $zhCnPlain,
            ]
        );

        // If no template with the Chinese default content exists, we don't need to set the system default.
        if (!\is_string($mailFooterIdZhCnCheck)) {
            return;
        }

        $englishHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-html.twig');
        $englishPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-plain.twig');

        // Check if a template contains the English default content.
        // The ID of the oldest one will be returned as this is the one, created during the installation.
        $mailFooterIdEnglishCheck = $connection->fetchOne(
            'SELECT id FROM mail_header_footer
             INNER JOIN mail_header_footer_translation
                ON mail_header_footer.id = mail_header_footer_translation.mail_header_footer_id
             WHERE mail_header_footer_translation.footer_html = :englishHtml
             AND mail_header_footer_translation.footer_plain = :englishPlain
             ORDER BY mail_header_footer_translation.created_at ASC
             LIMIT 1',
            [
                'englishHtml' => $englishHtml,
                'englishPlain' => $englishPlain,
            ]
        );

        // If no template with the English default content exists, we don't need to set the system default.
        if (!\is_string($mailFooterIdEnglishCheck)) {
            return;
        }

        // If both checks are returning the same ID, we can set this template as the system default.
        if ($mailFooterIdZhCnCheck === $mailFooterIdEnglishCheck) {
            $connection->update(
                MailHeaderFooterDefinition::ENTITY_NAME,
                ['system_default' => 1],
                ['id' => $mailFooterIdZhCnCheck]
            );
        }
    }
}
