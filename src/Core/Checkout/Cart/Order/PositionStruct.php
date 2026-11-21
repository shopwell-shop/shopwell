<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
final class PositionStruct extends Struct
{
    public function __construct(
        protected int $position
    ) {
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getApiAlias(): string
    {
        return 'cart_order_position';
    }
}
