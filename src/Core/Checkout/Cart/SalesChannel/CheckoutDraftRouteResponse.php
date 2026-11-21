<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CheckoutDraft>
 */
#[Package('checkout')]
class CheckoutDraftRouteResponse extends StoreApiResponse
{
    public function getDraft(): CheckoutDraft
    {
        return $this->object;
    }
}
