<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway;

use Shopwell\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
interface CheckoutGatewayInterface
{
    public function process(CheckoutGatewayPayloadStruct $payload): CheckoutGatewayResponse;
}
