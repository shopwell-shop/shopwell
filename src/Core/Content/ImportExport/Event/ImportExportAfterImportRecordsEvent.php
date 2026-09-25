<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Event;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\ImportExport\Struct\ImportResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportAfterImportRecordsEvent extends Event
{
    public function __construct(
        private readonly Config $config,
        private readonly Context $context,
        private readonly ImportResult $result,
    ) {
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getResult(): ImportResult
    {
        return $this->result;
    }
}
