<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Processing\Reader;

use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
abstract class AbstractReaderFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractReader;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
