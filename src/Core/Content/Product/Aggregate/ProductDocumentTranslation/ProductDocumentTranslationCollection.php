<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductDocumentTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductDocumentTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_document_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductDocumentTranslationEntity::class;
    }
}
