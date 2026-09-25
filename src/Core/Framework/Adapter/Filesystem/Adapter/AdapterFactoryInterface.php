<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface AdapterFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter;

    public function getType(): string;
}
