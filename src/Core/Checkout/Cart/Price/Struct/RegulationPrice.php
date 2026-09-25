<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\Framework\Util\FloatComparator;

#[Package('checkout')]
class RegulationPrice extends Struct
{
    public function __construct(
        protected float $price
    ) {
        $this->price = FloatComparator::cast($price);
    }

    public function getPrice(): float
    {
        return FloatComparator::cast($this->price);
    }

    public function getApiAlias(): string
    {
        return 'cart_regulation_price';
    }
}
