<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Exception;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWishlistNotFoundException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::WISHLIST_NOT_FOUND,
            'Wishlist for this customer was not found.'
        );
    }
}
