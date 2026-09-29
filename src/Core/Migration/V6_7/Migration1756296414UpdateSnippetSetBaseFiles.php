<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1756296414UpdateSnippetSetBaseFiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756296414;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            UPDATE `snippet_set`
            SET base_file = REPLACE(REPLACE(`base_file`, 'zh-CN', 'zh'), 'en-GB', 'en')
            WHERE `base_file` IN ('messages.zh-CN', 'messages.en-GB')
        SQL;

        $connection->executeStatement($sql);
    }
}
