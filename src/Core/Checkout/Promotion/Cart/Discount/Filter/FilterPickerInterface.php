<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
interface FilterPickerInterface
{
    public function getKey(): string;

    public function pickItems(DiscountPackageCollection $units): DiscountPackageCollection;
}
