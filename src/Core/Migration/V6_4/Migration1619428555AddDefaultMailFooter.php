<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;
use Shopwell\Core\Migration\Traits\Translations;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1619428555AddDefaultMailFooter extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1619428555;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $id = Uuid::randomBytes();

        $connection->insert(MailHeaderFooterDefinition::ENTITY_NAME, [
            'id' => $id,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'system_default' => 1,
        ]);

        $translations = new Translations(
            [
                'mail_header_footer_id' => $id,
                'name' => 'Standard-E-Mail-Fußzeile',
                'description' => 'Standard-E-Mail-Fußzeile basierend auf den Stammdaten',
                'header_html' => null,
                'header_plain' => null,
                'footer_plain' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-plain.twig'),
                'footer_html' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-html.twig'),
            ],
            [
                'mail_header_footer_id' => $id,
                'name' => 'Default email footer',
                'description' => 'Default email footer derived from basic information',
                'header_html' => null,
                'header_plain' => null,
                'footer_plain' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-plain.twig'),
                'footer_html' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-html.twig'),
            ]
        );

        $this->importTranslation(MailHeaderFooterTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
