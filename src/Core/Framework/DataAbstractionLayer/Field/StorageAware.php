<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface StorageAware
{
    public function getStorageName(): string;
}
