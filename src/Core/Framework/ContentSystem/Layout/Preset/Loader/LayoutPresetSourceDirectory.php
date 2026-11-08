<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class LayoutPresetSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
        public string $prefix,
    ) {
    }
}
