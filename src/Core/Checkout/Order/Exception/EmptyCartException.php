<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Exception;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class EmptyCartException extends CartException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::CART_EMPTY,
            'Cart is empty.',
        );
    }
}
