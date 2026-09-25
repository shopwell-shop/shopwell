<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class Migration1712309989DropLanguageLocaleUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1712309989;
    }

    public function update(Connection $connection): void
    {
        $this->dropIndexIfExists($connection, 'language', 'uniq.translation_code_id');
    }
}
