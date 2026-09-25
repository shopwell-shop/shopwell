<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Filesystem\Plugin;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface WriteBatchInterface
{
    public function writeBatch(CopyBatchInput ...$files): void;
}
