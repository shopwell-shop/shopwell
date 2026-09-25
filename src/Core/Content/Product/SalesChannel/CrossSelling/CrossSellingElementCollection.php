<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\CrossSelling;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CrossSellingElement>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class CrossSellingElementCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cross_selling_elements';
    }

    protected function getExpectedClass(): ?string
    {
        return CrossSellingElement::class;
    }
}
