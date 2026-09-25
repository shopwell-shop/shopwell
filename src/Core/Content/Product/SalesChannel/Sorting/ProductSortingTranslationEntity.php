<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Sorting;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSortingTranslationEntity extends TranslationEntity
{
    use EntityIdTrait;

    protected string $productSortingId;

    protected ?ProductSortingEntity $productSorting = null;

    protected ?string $label = null;

    public function getProductSortingId(): string
    {
        return $this->productSortingId;
    }

    public function setProductSortingId(string $productSortingId): void
    {
        $this->productSortingId = $productSortingId;
    }

    public function getProductSorting(): ?ProductSortingEntity
    {
        return $this->productSorting;
    }

    public function setProductSorting(?ProductSortingEntity $productSorting): void
    {
        $this->productSorting = $productSorting;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getApiAlias(): string
    {
        return 'product_sorting_translation';
    }
}
