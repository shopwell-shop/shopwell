<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233400MailTemplateMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233400;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `mail_template_media` (
                `id` BINARY(16) NOT NULL,
                `mail_template_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) DEFAULT NULL,
                `media_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT '1',
                PRIMARY KEY (id),
                KEY `fk.mail_template_media.mail_template_id` (`mail_template_id`),
                KEY `fk.mail_template_media.media_id` (`media_id`),
                KEY `fk.mail_template_media.language_id` (`language_id`),
                CONSTRAINT `fk.mail_template_media.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.mail_template_media.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.mail_template_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
