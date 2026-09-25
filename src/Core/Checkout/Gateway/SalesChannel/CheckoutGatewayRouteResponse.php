<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\SalesChannel;

use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{payments: PaymentMethodCollection, shipments: ShippingMethodCollection, errors: ErrorCollection}>>
 */
#[Package('checkout')]
class CheckoutGatewayRouteResponse extends StoreApiResponse
{
    public function __construct(
        private PaymentMethodCollection $payments,
        private ShippingMethodCollection $shipments,
        private ErrorCollection $errors,
    ) {
        parent::__construct(new ArrayStruct([
            'payments' => $payments,
            'shipments' => $shipments,
            'errors' => $errors,
        ]));
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->payments;
    }

    public function setPaymentMethods(PaymentMethodCollection $payments): void
    {
        $this->payments = $payments;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shipments;
    }

    public function setShippingMethods(ShippingMethodCollection $shipments): void
    {
        $this->shipments = $shipments;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->errors;
    }

    public function setErrors(ErrorCollection $errors): void
    {
        $this->errors = $errors;
    }
}
