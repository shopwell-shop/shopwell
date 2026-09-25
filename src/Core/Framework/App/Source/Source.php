<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Source;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
interface Source
{
    public static function name(): string;

    public function supports(AppEntity|Manifest $app): bool;

    public function filesystem(AppEntity|Manifest $app): Filesystem;

    /**
     * @param array<Filesystem> $filesystems
     */
    public function reset(array $filesystems): void;
}
