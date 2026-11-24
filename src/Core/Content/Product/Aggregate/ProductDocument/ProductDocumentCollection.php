<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductDocument;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductDocumentEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductDocumentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_document_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductDocumentEntity::class;
    }
}
