<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Exception;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InvalidImitateCustomerTokenException extends CustomerException
{
    public function __construct(string $token)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::IMITATE_CUSTOMER_INVALID_TOKEN,
            'The token "{{ token }}" is invalid.',
            ['token' => $token]
        );
    }
}
