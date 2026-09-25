<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;

#[Package('checkout')]
interface FilterableInterface
{
    public function getFilter(): ?Rule;
}
