<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductNumberNotFoundException extends ShopwellHttpException
{
    public function __construct(string $number)
    {
        parent::__construct(
            'Product with number "{{ number }}" not found.',
            ['number' => $number]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
