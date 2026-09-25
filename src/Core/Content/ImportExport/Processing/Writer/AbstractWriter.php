<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Processing\Writer;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('fundamentals@after-sales')]
abstract class AbstractWriter
{
    /**
     * @param array<string, mixed> $data
     */
    abstract public function append(Config $config, array $data, int $index): void;

    abstract public function flush(Config $config, string $targetPath): void;

    abstract public function finish(Config $config, string $targetPath): void;

    protected function getDecorated(): AbstractWriter
    {
        throw new DecorationPatternException(self::class);
    }
}
