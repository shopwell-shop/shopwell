<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductManufacturerEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductManufacturerCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(static fn (ProductManufacturerEntity $productManufacturer) => $productManufacturer->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(static fn (ProductManufacturerEntity $productManufacturer) => $productManufacturer->getMediaId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_manufacturer_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerEntity::class;
    }
}
