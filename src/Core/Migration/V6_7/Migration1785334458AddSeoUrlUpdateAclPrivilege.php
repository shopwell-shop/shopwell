<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1785334458AddSeoUrlUpdateAclPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'product.editor' => [
            'seo_url:update',
        ],
        'category.editor' => [
            'seo_url:update',
        ],
        'landing_page.editor' => [
            'seo_url:update',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1785334458;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
