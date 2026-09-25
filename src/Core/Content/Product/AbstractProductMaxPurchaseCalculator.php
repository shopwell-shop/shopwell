<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractProductMaxPurchaseCalculator
{
    abstract public function getDecorated(): AbstractProductMaxPurchaseCalculator;

    abstract public function calculate(Entity $product, SalesChannelContext $context): int;
}
