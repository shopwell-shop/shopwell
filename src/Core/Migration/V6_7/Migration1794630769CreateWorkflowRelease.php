<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794630769CreateWorkflowRelease extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794630769;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_release` (
    `id` BINARY(16) NOT NULL,
    `draft_id` BINARY(16) NOT NULL,
    `revision` INT NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `publisher` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `published_by` BINARY(16) NOT NULL,
    `document` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.workflow_release.source` (`draft_id`, `revision`, `publisher`),
    CONSTRAINT `json.workflow_release.document` CHECK (JSON_VALID(`document`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
