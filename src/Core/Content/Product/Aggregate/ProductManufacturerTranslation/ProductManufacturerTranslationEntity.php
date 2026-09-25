<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductManufacturerTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $productManufacturerId;

    protected string $productManufacturerVersionId;

    protected ?string $name = null;

    protected ?string $link = null;

    protected ?string $description = null;

    protected ?ProductManufacturerEntity $productManufacturer = null;

    public function getProductManufacturerId(): string
    {
        return $this->productManufacturerId;
    }

    public function setProductManufacturerId(string $productManufacturerId): void
    {
        $this->productManufacturerId = $productManufacturerId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getProductManufacturer(): ?ProductManufacturerEntity
    {
        return $this->productManufacturer;
    }

    public function setProductManufacturer(ProductManufacturerEntity $productManufacturer): void
    {
        $this->productManufacturer = $productManufacturer;
    }

    public function getProductManufacturerVersionId(): string
    {
        return $this->productManufacturerVersionId;
    }

    public function setProductManufacturerVersionId(string $productManufacturerVersionId): void
    {
        $this->productManufacturerVersionId = $productManufacturerVersionId;
    }
}
