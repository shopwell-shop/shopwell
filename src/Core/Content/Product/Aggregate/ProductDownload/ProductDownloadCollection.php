<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductDownload;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductDownloadEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductDownloadCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_download_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductDownloadEntity::class;
    }
}
