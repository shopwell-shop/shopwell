<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1774345867AddProductOpenGraphFields extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1774345867;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'product',
            column: 'open_graph_media_id',
            type: 'BINARY(16)',
            nullable: true,
            default: 'NULL',
        );

        $this->addColumn(
            connection: $connection,
            table: 'product_translation',
            column: 'og_title',
            type: 'VARCHAR(255)',
            nullable: true,
            default: 'NULL',
        );

        $this->addColumn(
            connection: $connection,
            table: 'product_translation',
            column: 'og_description',
            type: 'VARCHAR(255)',
            nullable: true,
            default: 'NULL',
        );

        if (!$this->indexExists($connection, 'product', 'fk.product.open_graph_media_id')) {
            $this->executeDdlStatement(
                $connection,
                'ALTER TABLE `product`
                ADD CONSTRAINT `fk.product.open_graph_media_id`
                    FOREIGN KEY (`open_graph_media_id`)
                    REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }

        if (!TableHelper::columnExists($connection, 'product', 'openGraphMedia')) {
            $this->updateInheritance($connection, 'product', 'openGraphMedia');
        }

        $this->registerIndexer($connection, 'product.indexer', ['product.inheritance']);
    }
}
