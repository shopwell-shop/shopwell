<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\SalesChannel\Struct;

use Shopwell\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CrossSellingStruct extends Struct
{
    protected ?CrossSellingElementCollection $crossSellings = null;

    public function getCrossSellings(): ?CrossSellingElementCollection
    {
        return $this->crossSellings;
    }

    public function setCrossSellings(CrossSellingElementCollection $crossSellings): void
    {
        $this->crossSellings = $crossSellings;
    }

    public function getApiAlias(): string
    {
        return 'cms_cross_selling';
    }
}
