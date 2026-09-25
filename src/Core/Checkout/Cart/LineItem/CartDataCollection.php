<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<mixed>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CartDataCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cart_data_collection';
    }
}
