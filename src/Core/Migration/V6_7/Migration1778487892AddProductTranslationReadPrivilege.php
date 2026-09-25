<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1778487892AddProductTranslationReadPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'product.viewer' => [
            'product_translation:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1778487892;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
