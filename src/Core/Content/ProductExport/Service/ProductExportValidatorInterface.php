<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Service;

use Shopwell\Core\Content\ProductExport\Error\Error;
use Shopwell\Core\Content\ProductExport\ProductExportEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductExportValidatorInterface
{
    /**
     * @return list<Error>
     */
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array;
}
