<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SuccessResponse;

#[Package('checkout')]
class SetPaymentOrderRouteResponse extends SuccessResponse
{
}
