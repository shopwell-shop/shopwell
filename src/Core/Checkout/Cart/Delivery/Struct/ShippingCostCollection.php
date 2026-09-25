<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<ShippingCost>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ShippingCostCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ShippingCost::class;
    }
}
