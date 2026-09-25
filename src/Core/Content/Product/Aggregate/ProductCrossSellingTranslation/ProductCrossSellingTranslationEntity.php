<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingTranslation;

use Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductCrossSellingTranslationEntity extends TranslationEntity
{
    protected string $productCrossSellingId;

    protected ?string $name = null;

    protected ?ProductCrossSellingEntity $productCrossSelling = null;

    public function getProductCrossSellingId(): string
    {
        return $this->productCrossSellingId;
    }

    public function setProductCrossSellingId(string $productCrossSellingId): void
    {
        $this->productCrossSellingId = $productCrossSellingId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getProductCrossSelling(): ?ProductCrossSellingEntity
    {
        return $this->productCrossSelling;
    }

    public function setProductCrossSelling(ProductCrossSellingEntity $productCrossSelling): void
    {
        $this->productCrossSelling = $productCrossSelling;
    }
}
