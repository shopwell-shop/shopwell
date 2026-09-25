<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<InvalidPluralizationStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class InvalidPluralizationCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return InvalidPluralizationStruct::class;
    }
}
