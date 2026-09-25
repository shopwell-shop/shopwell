<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to handle the payment for an order.
 */
#[Package('checkout')]
abstract class AbstractHandlePaymentMethodRoute
{
    abstract public function getDecorated(): AbstractHandlePaymentMethodRoute;

    abstract public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse;
}
