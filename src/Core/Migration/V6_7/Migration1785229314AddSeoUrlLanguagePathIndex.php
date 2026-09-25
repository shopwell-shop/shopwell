<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1785229314AddSeoUrlLanguagePathIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785229314;
    }

    public function update(Connection $connection): void
    {
        // The per-request SEO resolver (SeoResolver::resolve) filters `language_id = ? AND seo_path_info IN (?, ?)`
        // with a `sales_channel_id = ? OR sales_channel_id IS NULL` clause. The existing unique index
        // `(language_id, sales_channel_id, seo_path_info)` leads with sales_channel_id before seo_path_info, so the
        // OR on that middle column prevents a seek on the highly selective seo_path_info. This index puts the two
        // equality predicates first so the lookup becomes a seek instead of a per-language range scan.
        if (TableHelper::indexExists($connection, 'seo_url', 'idx.seo_url.language_path')) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `idx.seo_url.language_path` ON `seo_url` (`language_id`, `seo_path_info`)'
        );
    }
}
