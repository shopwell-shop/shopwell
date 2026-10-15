<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<Cart>
 */
#[Package('checkout')]
class WalletPaymentRouteResponse extends StoreApiResponse
{
    public function getCart(): Cart
    {
        return $this->object;
    }
}
