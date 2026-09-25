<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Service;

use Shopwell\Core\Content\ProductExport\Exception\ExportInvalidException;
use Shopwell\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopwell\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
interface ProductExporterInterface
{
    /**
     * @throws ExportInvalidException
     * @throws ExportNotFoundException
     */
    public function export(
        SalesChannelContext $context,
        ExportBehavior $behavior,
        ?string $productExportId = null
    ): void;
}
