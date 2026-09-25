<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Detail;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractAvailableCombinationLoader
{
    abstract public function getDecorated(): AbstractAvailableCombinationLoader;

    abstract public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult;
}
