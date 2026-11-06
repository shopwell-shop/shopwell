<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
class SelectedPrice extends Struct
{
    public function __construct(
        protected float $value,
        protected bool $isCalculated
    ) {
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function isCalculated(): bool
    {
        return $this->isCalculated;
    }
}
