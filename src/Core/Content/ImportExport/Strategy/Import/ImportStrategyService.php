<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Strategy\Import;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\ImportExport\Struct\ImportResult;
use Shopwell\Core\Content\ImportExport\Struct\Progress;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
interface ImportStrategyService
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     */
    public function import(
        array $record,
        array $row,
        Config $config,
        Progress $progress,
        Context $context,
    ): ImportResult;

    public function commit(Config $config, Progress $progress, Context $context): ImportResult;
}
