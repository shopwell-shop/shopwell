<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductCloseoutFilterFactory extends AbstractProductCloseoutFilterFactory
{
    public function getDecorated(): AbstractProductCloseoutFilterFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(SalesChannelContext $context): MultiFilter
    {
        return new ProductCloseoutFilter();
    }
}
