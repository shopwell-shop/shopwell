<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StoreTokenMissingException extends StoreException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            self::STORE_TOKEN_IS_MISSING,
            'Store token is missing'
        );
    }
}
