<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Exception;

use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class WrongGuestCredentialsException extends OrderException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            parent::CHECKOUT_GUEST_WRONG_CREDENTIALS,
            'Wrong credentials for guest authentication.'
        );
    }
}
