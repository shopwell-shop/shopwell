<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class SetGroupScopeFilter
{
    abstract public function getDecorated(): SetGroupScopeFilter;

    abstract public function filter(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountPackageCollection;
}
