<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Exception;

use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - will be removed. Use OrderException::paymentMethodNotChangeable instead
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PaymentMethodNotChangeableException extends OrderException
{
    public function __construct(string $id)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'CHECKOUT__PAYMENT_METHOD_UNCHANGEABLE',
            'The order has an active transaction - {{ id }}',
            ['id' => $id]
        );
    }
}
