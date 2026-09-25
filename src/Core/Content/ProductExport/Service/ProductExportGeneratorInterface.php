<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Service;

use Shopwell\Core\Content\ProductExport\ProductExportEntity;
use Shopwell\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopwell\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductExportGeneratorInterface
{
    public function generate(
        ProductExportEntity $productExport,
        ExportBehavior $exportBehavior
    ): ?ProductExportResult;
}
