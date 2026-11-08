<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ElementTypeSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
        public string $prefix,
    ) {
    }
}
