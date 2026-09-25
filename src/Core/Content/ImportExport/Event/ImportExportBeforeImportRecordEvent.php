<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Event;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportBeforeImportRecordEvent extends Event
{
    /**
     * @param array<array-key, mixed> $record
     * @param array<string, mixed> $row
     */
    public function __construct(
        private array $record,
        private readonly array $row,
        private readonly Config $config,
        private readonly Context $context,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
