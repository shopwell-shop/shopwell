<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Processing\Reader;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
abstract class AbstractReader
{
    /**
     * @param resource $resource
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function read(Config $config, $resource, int $offset): iterable;

    abstract public function getOffset(): int;

    protected function getDecorated(): AbstractReader
    {
        throw new \RuntimeException('Implement getDecorated');
    }
}
