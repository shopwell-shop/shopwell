<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\SalesChannel\Struct;

use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ManufacturerLogoStruct extends ImageStruct
{
    protected ?ProductManufacturerEntity $manufacturer = null;

    public function getManufacturer(): ?ProductManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?ProductManufacturerEntity $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getApiAlias(): string
    {
        return 'cms_manufacturer_logo';
    }
}
