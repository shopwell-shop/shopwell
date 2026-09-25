<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Processing\Writer;

use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
abstract class AbstractWriterFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractWriter;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
