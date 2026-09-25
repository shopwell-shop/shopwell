<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractSystemConfigLoader
{
    abstract public function getDecorated(): AbstractSystemConfigLoader;

    /**
     * @return array<string, mixed>
     */
    abstract public function load(?string $salesChannelId): array;
}
