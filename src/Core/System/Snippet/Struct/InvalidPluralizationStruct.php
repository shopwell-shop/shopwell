<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class InvalidPluralizationStruct extends Struct
{
    public function __construct(
        public readonly string $snippetKey,
        public readonly string $snippetValue,
        public readonly bool $isFixable,
        public readonly string $path,
    ) {
    }
}
