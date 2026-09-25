<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<DomainRuleStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class DomainRuleCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return DomainRuleStruct::class;
    }
}
