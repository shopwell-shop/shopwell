<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream\Aggregate\ProductStreamTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductStreamTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getProductStreamIds(): array
    {
        return $this->fmap(static fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getProductStreamId());
    }

    public function filterByProductStreamId(string $id): self
    {
        return $this->filter(static fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getProductStreamId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_stream_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamTranslationEntity::class;
    }
}
