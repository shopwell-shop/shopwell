<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1794221136AddProductDocumentMediaUsagePrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794221136;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, [
            'media:read' => [
                'product_document:read',
                'product_document_translation:read',
            ],
        ]);
    }
}
