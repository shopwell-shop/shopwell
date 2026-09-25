<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PriceDefinitionNotValidForDiscountTypeException extends ShopwellHttpException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_PRICE_DEFINITION_FOR_DISCOUNT_TYPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
