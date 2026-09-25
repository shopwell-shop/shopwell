<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Exception;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class BadCredentialsException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::CUSTOMER_AUTH_BAD_CREDENTIALS,
            'Invalid username and/or password.'
        );
    }
}
