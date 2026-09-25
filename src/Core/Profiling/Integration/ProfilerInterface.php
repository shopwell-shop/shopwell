<?php declare(strict_types=1);

namespace Shopwell\Core\Profiling\Integration;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal experimental atm
 */
#[Package('framework')]
interface ProfilerInterface
{
    public function start(string $title, string $category, array $tags): void;

    public function stop(string $title): void;
}
